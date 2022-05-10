import fs from "fs";
import path from "path";
import * as util from "./utils";
import * as videocdn from "./videocdn";
import * as dataTransformer from "./dataTransformer";
import * as kinoDb from "./kinodb";
import * as poster from "./poster";
import * as kinopoisk from "./kinopoisk";
import { exec } from "child_process";


// используется для мониторинга используемой памяти
// в MB
let MEMORY_BEFORE: number = 0;

const IS_TEST = util.getConfig('is_test');

type VideocdnFilm = dataTransformer.videocdn.ResultFilm;


const start = async () => {
    // очищаем папку с файлами дебага
    clearDebug();

    // получает материалы с VIDEOCDN
    let movies = await getVideocdnMovies();
    // преобразуем данные
    let videocdnMovies = dataTransformer.videocdn.transformFilmDataArray(movies);

    // получаем информацию с кинопоиска
    let kinopoiskInfo = await getKinopoiskInfo(videocdnMovies);
    // преобразуем данные
    let kinopoiskMovies = dataTransformer.kinopoisk.transformFilmDataArray(kinopoiskInfo);

    // формируем конечный массив со всеми данными
    let films = dataTransformer.joinKinopoiskInfo(videocdnMovies, kinopoiskMovies);

    // получаем постеры
    let posters = await getPosters(films);

    // прикрепляеем постеры к массиву с фильмами
    dataTransformer.poster.joinPosters(films, posters);

    // обновляем фильмы в БД
    await updateFilms(films);

    // создаем дампы таблиц
    exec(`/bin/bash ${path.resolve(__dirname, '../export-tables.sh')}`, (error, stdout, stderr) => {
        if (error) {
            console.log(`error: ${error.message}`);
            return;
        }
        if (stderr) {
            console.log(`stderr: ${stderr}`);
            return;
        }
        console.log(`stdout: ${stdout}`);
    });
}


const updateFilms = async films => {
    console.log('START UPDATE TERMS');
    console.log(util.getCurrentDate());

    kinoDb.createPool({
        host: util.getConfig('db', 'host'),
        user: util.getConfig('db', 'user'),
        pass: util.getConfig('db', 'password'),
        db: util.getConfig('db', 'database')
    });

    await kinoDb.updateTerms(films);

    kinoDb.closePool();

    console.log('END UPDATE TERMS');
    console.log(util.getCurrentDate());

    console.log('START UPDATE FILMS');
    console.log(util.getCurrentDate());

    await kinoDb.updateFilms({
        films,
        chunks: 50,
        dbConn: {
            host: util.getConfig('db', 'host'),
            user: util.getConfig('db', 'user'),
            pass: util.getConfig('db', 'password'),
            db: util.getConfig('db', 'database')
        }
    });

    console.log('END UPDATE FILMS');
    console.log(util.getCurrentDate());

    return true;
}



const getPosters = async (films) => {
    console.log('START GET POSTERS');
    console.log(util.getCurrentDate());

    kinoDb.createPool({
        host: util.getConfig('db', 'host'),
        user: util.getConfig('db', 'user'),
        pass: util.getConfig('db', 'password'),
        db: util.getConfig('db', 'database')
    });

    let existsPosters = await kinoDb.getPosters();

    kinoDb.closePool();

    let filmPosters = await poster.getPosters({
        films,
        invalidPosters: util.getConfig('invalid_posters'),
        imdbUrl: util.getConfig('imdb', 'api_url'),
        pathPosters: path.join(__dirname, '../posters'),
        chunks: 50,
        existsPosters: existsPosters // массив постеров с базы данных
    });

    console.log('END GET POSTERS');
    console.log(util.getCurrentDate());

    return filmPosters;
}



const getVideocdnMovies = async (): Promise<Object[]> => {
    console.log(`START VIDEOCDN`);
    console.log(util.getCurrentDate());

    let args = {
        chunks: 30,
        config: util.getConfig('videocdn'),
        catsIds: util.getConfig('cats_ids'),
        limitRequests: -1,
        limitCats: -1
    };

    if (IS_TEST) {
        args.chunks = 1;
        args.limitRequests = 10;
        args.limitCats = 1;
        args.config = {
            api_token: args.config.api_token,
            url_show_tv_series: args.config.url_show_tv_series
        }
    }

    let movies = await videocdn.getMovies(args);

    console.log(`всего получено фильмов: ${movies.length}`);
    console.log(util.getCurrentDate());
    console.log(`END VIDEOCDN`);

    return movies;
}




const getKinopoiskInfo = async (movies: VideocdnFilm[]) => {
    let ids = Object.values(movies).map(film => film.kinopoisk_id).filter(item => item);

    if (!ids.length) {
        return [];
    }

    console.log(`START GET KINOPOISK`);
    console.log(util.getCurrentDate());

    let config = util.getConfig('kinopoisk');

    let kinopoiskInfo = await kinopoisk.getFilmsInfo({
        kinopoiskIds: ids,
        chunks: 6,
        config: {
            info_url: config.info_url,
            // trailer_url: config.trailer_url,
            participants_url: config.participants_url,
            frames_url: config.frames_url,
            token: config.token
        }
    });

    console.log(`всего ролучено фильмов ${kinopoiskInfo.length}`);
    console.log(util.getCurrentDate());
    console.log(`END GET KINOPOISK`);

    return kinopoiskInfo;
}



///////////////////////////////////////////////////////
//                  HELPER FUNCTIONS
//////////////////////////////////////////////////////

const createPool = () => {
    kinoDb.createPool({
        host: util.getConfig('db', 'host'),
        user: util.getConfig('db', 'user'),
        pass: util.getConfig('db', 'password'),
        db: util.getConfig('db', 'database')
    });
}


const closePool = () => {
    kinoDb.closePool();
}

export const saveFile = (name, content) => {
    if (typeof content !== 'string') {
        content = JSON.stringify(content, null, 4);
        name += '.json';
    }
    fs.writeFileSync(path.join(path.resolve(__dirname, '../', 'src'), name), content);
}


export const getFile = (name, isJson = true) => {
    if (isJson) {
        name += '.json';
    }
    let content = fs.readFileSync(path.join(path.resolve(__dirname, '../', 'src'), name)).toString();
    return isJson ? JSON.parse(content) : content;
}


const memoryStart = () => {
    MEMORY_BEFORE = getCurrentMemory();
}


const memoryEnd = () => {
    let used = getCurrentMemory();
    let diff = used - MEMORY_BEFORE;
    console.log(`USED MEMORY: ${diff} MB`);
}


const getCurrentMemory = (): number => {
    return Math.round((process.memoryUsage().heapUsed / 1024 / 1024) * 100) / 100;
}


export const debugFile = (pathName, fileName, content) => {
    let debugPath = path.resolve(__dirname, '../debug', pathName);

    if (!fs.existsSync(debugPath)) {
        fs.mkdirSync(debugPath);
    }

    let file = path.resolve(debugPath, `${fileName}.json`);

    fs.writeFileSync(file, JSON.stringify(content));
}


export const getDebugFiles = (pathName: string): any[] => {
    let folder = path.resolve(__dirname, '../debug', pathName);

    if (!fs.existsSync(folder)) {
        return [];
    }

    let scan = fs.readdirSync(folder);

    if (!scan || !scan.length) {
        return [];
    }

    let res = [];

    scan.forEach(fileName => {
        let file = path.resolve(folder, fileName);
        res.push(JSON.parse(fs.readFileSync(file).toString()));
    });

    return res;
}


const clearDebug = () => {
    let folder = path.resolve(__dirname, '../debug');
    let scan = fs.readdirSync(folder);

    if (!scan || !scan.length) {
        return;
    }

    scan.forEach(folderName => {
        let debugFolder = path.resolve(folder, folderName);
        fs.rmdirSync(debugFolder, { recursive: true });
    });
}




const actualizeTables = async () => {
    createPool();

    let exists = await kinoDb.simpleQuery(`SELECT ID FROM films`);
    let existsIds = exists.map(item => item.ID).join(',');

    await kinoDb.simpleQuery(`DELETE FROM cache WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM last_episodes WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM rel_actors WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM rel_composers WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM rel_countries WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM rel_directors WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM rel_genres WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM rel_qualities WHERE film_id NOT IN (${existsIds})`);
    await kinoDb.simpleQuery(`DELETE FROM rel_translations WHERE film_id NOT IN (${existsIds})`);

    closePool();
}



const clearDbDublicates = async () => {
    let data: string[];

    try {
        data = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../films_ids.json')).toString());
    } catch (e) {
        return false;
    }

    let validIds = data.join(',');

    createPool();

    let query = `
        SELECT kinopoisk_id, count(kinopoisk_id) as c
        FROM films
        GROUP BY kinopoisk_id
        HAVING c > 1
    `;

    let dublicates = await kinoDb.simpleQuery(query);

    if (!dublicates.length) {
        console.log(0);
        process.exit();
    }

    let toDelete = dublicates.map(item => item.kinopoisk_id);

    let idsToDelete = await kinoDb.simpleQuery(`SELECT ID FROM films WHERE kinopoisk_id IN (${toDelete.join(',')}) AND ID NOT IN (${validIds})`);
    idsToDelete = idsToDelete.map(item => item.ID);

    let total = idsToDelete.length;

    console.log(total);

    let relTables = ['rel_actors', 'rel_composers', 'rel_countries', 'rel_directors', 'rel_genres', 'rel_qualities', 'rel_translations'];

    kinoDb.simpleQuery(`SET foreign_key_checks = 0`);

    let deleted = 0;

    for (let i = 0; i < total; i++) {
        let id = idsToDelete[i];

        for (let c = 0; c < relTables.length; c++) {
            await kinoDb.simpleQuery(`DELETE FROM ${relTables[c]} WHERE film_id = ${id}`);
        }

        await kinoDb.simpleQuery(`DELETE FROM films WHERE ID = ${id}`);
        await kinoDb.simpleQuery(`DELETE FROM cache WHERE film_id = ${id}`);
        await kinoDb.simpleQuery(`DELETE FROM last_episodes WHERE film_id = ${id}`);

        deleted++;

        console.log(`${id} - DELETED (${deleted}/${total})`);
    }

    await kinoDb.simpleQuery(`SET foreign_key_checks = 1`);

    closePool();
}

start();