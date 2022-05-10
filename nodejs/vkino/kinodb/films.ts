import * as util from "../utils";
import * as mysql from "mysql2";
import { preparedQuery, simpleQuery } from "./index";


type DbConn = {
    host: string,
    user: string,
    pass: string,
    db: string
}

/**
 * - колонки в таблице films
 * - с ключами соответствия в объекте фильма
 */
const FILM_COLS: any = {
    ru_title: 'ru_title',
    orig_title: 'orig_title',
    en_title: 'en_title',
    description: 'description',
    cat_id: 'cat_id',
    iframe_src: 'iframe_src',
    trailer: 'trailer',
    duration: 'duration',
    poster: 'poster',
    frames: 'frames',
    world_premiere: 'world_premiere',
    budget: 'budget',
    rating_kinopoisk: 'rating_kinopoisk',
    rating_imdb: 'rating_imdb',
    year: 'year',
    imdb_id: 'imdb_id',
    kinopoisk_id: 'kinopoisk_id',
    videocdn_id: 'videocdn_id'
};

/**
 * - Таблицы связи терминов
 * - с ключами соответствия в объекте фильма
 */
const FILM_REL_TABLES: any = {
    rel_actors: 'actors',
    rel_composers: 'composers',
    rel_countries: 'country',
    rel_directors: 'directors',
    rel_genres: 'genre',
    rel_qualities: 'source_qualities',
    rel_translations: 'translations'
}


const FILM_CACHE_COLS: any = {
    ru_title: 'ru_title',
    orig_title: 'orig_title',
    en_title: 'en_title',
    description: 'description',
    cat_id: 'cat_id',
    iframe_src: 'iframe_src',
    trailer: 'trailer',
    duration: 'duration',
    poster: 'poster',
    frames: 'frames',
    world_premiere: 'world_premiere',
    budget: 'budget',
    rating_kinopoisk: 'rating_kinopoisk',
    rating_imdb: 'rating_imdb',
    year: 'year',
    imdb_id: 'imdb_id',
    kinopoisk_id: 'kinopoisk_id',
    videocdn_id: 'videocdn_id',

    last_episode: 'last_episode',

    actors: 'actors',
    composers: 'composers',
    countries: 'country',
    directors: 'directors',
    genres: 'genre',
    qualities: 'source_qualities',
    translations: 'translations'
}


/**
 * - Обновляет фильмы в базе данных
 *   разбивая процесс на цепочки
 */

export const updateFilms = async ({ films, chunks = 1, dbConn }: { films: Object[], chunks: number, dbConn: DbConn }) => {
    let filmsChunks = util.splitArr(films, chunks);
    let promises = [];

    for (let i = 0; i < filmsChunks.length; i++) {
        promises.push(updateFilmsChunk(filmsChunks[i], dbConn));
    }

    await Promise.all(promises);

    return true;
}


/**
 * - обновляет массив фильмов
 */
const updateFilmsChunk = async (films, dbConn: DbConn) => {
    let total = films.length;

    let pool = mysql.createPool({
        host: dbConn.host,
        user: dbConn.user,
        password: dbConn.pass,
        database: dbConn.db
    });

    for (let i = 0; i < total; i++) {
        await updateSingleFilm(films[i], pool);
    }

    pool.end();

    return true;
}


/**
 * - Обновляет отдельный фильм
 * - Создает новый если его нет
 */
const updateSingleFilm = async (film, pool?: Object) => {
    let id = await getFilmByKinopoiskId(film.kinopoisk_id, pool);

    if (id === false) {
        id = await insertFilm(film, pool);
    } else {
        await updateFilmInfo(film, id, pool);
    }

    if (!id) {
        console.log(`ERROR UPDATE FILM. KINOPOISK ID: ${film.kinopoisk_id}`);
        return false;
    }

    await updateFilmTerms(film, id, pool);
    await updateFilmLastEpisode(film, id, pool);

    await updateFilmCache(film, id, pool);

    console.log(`UPDATED FILM ${id}`);

    return true;
}


/**
 * - Обновляет фильм в таблице кэша
 */
const updateFilmCache = async (film, id: number, pool?: Object) => {
    await deleteFilmCache(id, pool);

    let { updated, created } = await getFilmDates(id, pool);

    let { insertSqlCols, insertValues, values } = getFilmSqlData(film, FILM_CACHE_COLS);

    insertSqlCols.push('film_id');
    insertSqlCols.push('updated');
    insertSqlCols.push('created');

    insertValues.push('?');
    insertValues.push('?');
    insertValues.push('?');

    values.push(id);
    values.push(updated);
    values.push(created);

    let query = `INSERT INTO cache (${insertSqlCols.join(',')}) VALUES (${insertValues.join(',')})`;

    let res = await preparedQuery(query, values, pool);

    return true;
}


const deleteFilmCache = async (filmId: number, pool?: Object) => {
    await simpleQuery(`DELETE FROM cache WHERE film_id = ${filmId}`, pool);
    return true;
}


/**
 * - Получает дату обновления и создания фильма
 */
const getFilmDates = async (id: number, pool?: Object) => {
    let res = await simpleQuery(`SELECT updated, created FROM films WHERE ID = ${id} LIMIT 1`, pool);
    if (!res || !res.length) {
        return { updated: null, created: null };
    }
    return { updated: res[0].updated, created: res[0].created };
}


const updateFilmLastEpisode = async (film, filmId: number, pool?: Object) => {
    await deleteFilmLastEpisode(filmId, pool);
    if (film.last_episode) {
        await insertFilmLastEpisode(film.last_episode, filmId, pool);
    }
}


const deleteFilmLastEpisode = async (filmId: number, pool?: Object) => {
    let query = `DELETE FROM last_episodes WHERE film_id = ?`;
    let res = await preparedQuery(query, [filmId], pool);
    return true;
}


const insertFilmLastEpisode = async (episode, filmId: number, pool?: Object) => {
    if (!episode.season_num || !episode.episode_num) {
        return false;
    }

    let query = `
    INSERT INTO last_episodes 
    (film_id, season_num, episode_num, ru_title, orig_title)
    VALUES (?, ?, ?, ?, ?)
    `;

    let values = [
        filmId,
        episode.season_num,
        episode.episode_num,
        episode.ru_title || null,
        episode.orig_title || null
    ];

    let res = await preparedQuery(query, values, pool);

    return true;
}


/**
 * - Обновляет термины фильма
 */
const updateFilmTerms = async (film, id: number, pool?: Object): Promise<boolean> => {

    for (let table in FILM_REL_TABLES) {
        let key = FILM_REL_TABLES[table];

        await deleteFilmTerms(id, table, pool);

        if (film[key] && film[key].length) {
            await addFilmTerms(id, table, film[key], pool);
        }
    }

    return true;
}


const deleteFilmTerms = async (id: number, table: string, pool?: Object) => {
    let query = `DELETE FROM ${table} WHERE film_id = ?`;
    let res = await preparedQuery(query, [id], pool);
    return true;
}


const addFilmTerms = async (id: number, table: string, terms: [{ id: number }], pool?: Object) => {
    let total = terms.length;

    for (let i = 0; i < total; i++) {
        let termId = terms[i].id;
        if (!termId) {
            continue;
        }
        await insertFilmTerm(id, termId, table, pool);
    }

    return true;
}


const insertFilmTerm = async (filmId: number, termId: number, table: string, pool?: Object) => {
    let query = `INSERT INTO ${table} (film_id, term_id) VALUES (?, ?)`;
    let res = await preparedQuery(query, [filmId, termId], pool);
    return true;
}


/**
 * - Вставляет новый фильм
 */
const insertFilm = async (film, pool?: Object): Promise<false | number> => {
    let { insertSqlCols, insertValues, values } = getFilmSqlData(film);

    let query = `INSERT INTO films (${insertSqlCols.join(',')}) VALUES (${insertValues.join(',')})`;

    let res = await preparedQuery(query, values, pool);

    if (res && res.insertId) {
        return res.insertId;
    }

    return false;
}


/**
 * - Обновляет существующий фильм
 */
const updateFilmInfo = async (filmData, filmId, pool?: Object) => {
    let isChanged = await isFilmChanged(filmData, filmId, pool);

    let { updateSqlCols, values } = getFilmSqlData(filmData);

    if (isChanged) {
        updateSqlCols.push(`updated = ?`);
        values.push(util.getCurrentDate());
    }

    let query = `UPDATE films SET ${updateSqlCols.join(', ')} WHERE ID = ${filmId}`;

    let res = await preparedQuery(query, values, pool);

    return true;
}


const getFilmSqlData = (film, colsRelations = null) => {
    let cols = colsRelations ? colsRelations : FILM_COLS;

    let updateSqlCols = [];
    let insertSqlCols = [];
    let insertValues = [];

    let values = [];

    for (let key in cols) {
        updateSqlCols.push(`${key} = ?`);
        insertSqlCols.push(`${key}`);

        let val = film[cols[key]];

        if (typeof val === 'object' && val !== null) {
            val = JSON.stringify(val);
        }

        if (!val) {
            val = null;
        }

        insertValues.push('?');
        values.push(val);
    }

    return { updateSqlCols, insertSqlCols, insertValues, values };
}


/**
 * - Определяет изменились ли следующие данные:
 *   - qualities
 *   - translations
 *   - last episode
 */
const isFilmChanged = async (filmData, filmId: number, pool?: Object): Promise<boolean> => {
    let relCheck = [
        {
            dataKey: 'qualities',
            table: 'rel_qualities'
        },
        {
            dataKey: 'translations',
            table: 'rel_translations'
        }
    ];

    for (let i = 0; i < relCheck.length; i++) {
        let check = relCheck[i];

        if (await isRelChanged(filmId, filmData[check.dataKey], check.table, pool)) {
            return true;
        }
    }

    let currentLastEpisod = await getLastEpisode(filmId, pool);
    let newLastEpisod = filmData.last_episode;

    if (!currentLastEpisod && !newLastEpisod) {
        return false;
    }

    if (currentLastEpisod && newLastEpisod) {
        if (
            currentLastEpisod.season_num === newLastEpisod.season_num
            &&
            currentLastEpisod.episode_num === newLastEpisod.episode_num
        ) {
            return false;
        }
    }

    return true;
}


/**
 * - Получает последний эпизод из бд
 */
const getLastEpisode = async (filmId, pool?: Object) => {
    let query = `SELECT * FROM last_episodes WHERE film_id = ? LIMIT 1`;
    let res = await preparedQuery(query, [filmId], pool);
    if (res && res.length) {
        return res[0];
    }
    return false;
}


/**
 * - Проверяет изменились ли термины
 */
const isRelChanged = async (filmId: number, data, relTable: string, pool?: Object): Promise<boolean> => {
    let query = `
    SELECT term_id
    FROM ${relTable}
    WHERE film_id = ?
    `;

    let current = await preparedQuery(query, [filmId], pool);
    if (current && current.length) {
        current = current.map(item => item.term_id);
    } else {
        current = [];
    }

    let newData = [];
    if (data && data.length) {
        newData = data.map(item => item.id);
    }

    if (current.length !== newData.length) {
        return true;
    }

    return !current.every((val, index) => val === newData[index]);
}


/**
 * - Получает фильм по кинопоиск ID
 */
const getFilmByKinopoiskId = async (kinopoiskId: string, pool?: Object): Promise<false | number> => {
    let query = `SELECT ID FROM films WHERE kinopoisk_id LIKE ? LIMIT 1`;

    let res = await preparedQuery(query, [kinopoiskId], pool);

    if (!res || !res.length) {
        return false;
    }

    return res[0].ID;
}
