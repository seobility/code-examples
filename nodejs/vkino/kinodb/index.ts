import * as mysql from "mysql2";
import * as util from "../utils";
import * as sqlstring from "sqlstring";
export { updateTerms } from "./terms";
export { updateFilms } from "./films";


let MYSQL_POOL = null;


export const createPool = ({ host, user, pass, db }) => {
    MYSQL_POOL = mysql.createPool({
        host: host,
        user: user,
        password: pass,
        database: db
    });
}


export const closePool = () => {
    if (!checkConnection()) return;
    MYSQL_POOL.end();
}


/**
 * - Получает все постеры с базы данных
 * @return array
 */
type Poster = {
    poster: string,
    film_id: number,
    kinopoisk_id: string
}
export const getPosters = async (): Promise<Poster[]> => {
    if (!checkConnection()) return [];
    let query = `
    SELECT films.poster, films.ID, films.kinopoisk_id 
    FROM films 
    WHERE films.poster IS NOT NULL
    `;
    return await simpleQuery(query);
}


const checkConnection = (): boolean => {
    if (MYSQL_POOL === null) {
        console.log(new Error(`ERROR! CONNECTION IS EMPTY!`));
        return false;
    }
    return true;
}


export const simpleQuery = async (query: string, pool?: Object): Promise<any> => {
    return new Promise(resolve => {
        let mysqlPool = pool ? pool : MYSQL_POOL;
        mysqlPool.query(query, (err, results, fields) => {
            if (err) {
                console.log(new Error(`ERROR MYSQL QUERY`));
                console.log(query);
                console.log(err);
                resolve(null);
            } else {
                resolve(results);
            }
        });
    });
}


export const preparedQuery = async (query: string, values: any[], pool?: Object): Promise<any> => {
    return new Promise(async resolve => {
        let formatQuery = sqlstring.format(query, values);
        let res = await simpleQuery(formatQuery, pool);
        resolve(res);
    });
}
