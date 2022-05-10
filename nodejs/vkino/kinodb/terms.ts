import { preparedQuery } from ".";


type Term = {
    slug: string,
    name: string
}

type TermDb = {
    slug: string,
    name: string,
    id: number
}


/**
 * - Обновляет термины в бызе данных
 * - Создает новые если их нет
 * - Изменяет входящий массив добавляя каждому термину ID из БД
 */
export const updateTerms = async (films: any[]) => {
    let promises = [
        updateRelTerms('translations', 'translations', 'rel_translations', films),
        updateRelTerms('source_qualities', 'qualities', 'rel_qualities', films),
        updateRelTerms('country', 'countries', 'rel_countries', films),
        updateRelTerms('genre', 'genres', 'rel_genres', films),
        updateRelTerms('actors', 'actors', 'rel_actors', films),
        updateRelTerms('composers', 'composers', 'rel_composers', films),
        updateRelTerms('directors', 'directors', 'rel_directors', films)
    ];

    await Promise.all(promises);

    return true;
}


const updateRelTerms = async (key: string, table: string, relTable: string, films: any[]) => {
    let total = films.length;
    let terms = {};

    for (let i = 0; i < total; i++) {
        if (!films[i][key] || !films[i][key].length) {
            continue;
        }
        films[i][key] = await updateTermsArr(films[i][key], table, terms);
    }
}



const updateTermsArr = async (termsArr: Term[], table: string, terms: Object): Promise<TermDb[]> => {
    let total = termsArr.length;
    let res: TermDb[] = [] as any;

    for (let i = 0; i < total; i++) {
        let curTerm = termsArr[i];
        let slug = curTerm.slug;

        if (terms[slug]) {
            res.push({ ...curTerm, id: terms[slug] });
            continue;
        }

        let id = await updateTerm(curTerm, table);

        if (!id) {
            continue;
        }

        terms[slug] = id;

        res.push({ ...curTerm, id });
    }

    return res;
}


const updateTerm = async (term: Term, table: string): Promise<number | false> => {
    let id = await getTermBySlug(term.slug, table);
    if (id) {
        return id;
    }
    id = await insertTerm(term, table);
    return id;
}


const getTermBySlug = async (slug: string, table: string): Promise<number | false> => {
    let query = `SELECT ID FROM ${table} WHERE slug LIKE ? LIMIT 1`;
    let res = await preparedQuery(query, [slug]);

    if (!res || !res.length) return false;

    return res[0].ID;
}


const insertTerm = async (term: Term, table: string): Promise<number | false> => {
    let cols = Object.keys(term);
    let values = Object.values(term);

    let sqlCols = cols.join(', ');
    let sqlValues = values.map(val => '?').join(', ');

    let query = `INSERT INTO ${table} (${sqlCols}) VALUES (${sqlValues})`;

    let res = await preparedQuery(query, values);

    if (res && res.insertId) {
        return res.insertId;
    }

    return false;
}
