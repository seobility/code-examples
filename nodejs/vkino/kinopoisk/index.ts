import axios from "axios";
import * as util from "../utils";
import { debugFile } from "../index";


let URL_INFO: string | null = null;

let URL_TRAILER: string | null = null;

let URL_PARTICIPANTS: string | null = null;

let URL_FRAMES: string | null = null;

let URL_REVIEWS: string | null = null;

let URL_REVIEWS_DETAIL: string | null = null;

let TOKEN: string[] = [];



type Config = {
    token: string,
    info_url?: string,
    trailer_url?: string,
    participants_url?: string,
    frames_url?: string,
    reviews_url?: string,
    reviews_detail_url?: string
}

type ResFilm = {
    info?: Object,
    trailer?: Object,
    participants?: Object | Object[],
    frames?: Object,
    reviews?: Object,
    kinopoisk_id: string
}



export const getFilmsInfo = async (params: { kinopoiskIds: string[], chunks?: number, config: Config }) => {
    let { kinopoiskIds, chunks = 0, config } = params;

    URL_INFO = config.info_url || null;
    URL_TRAILER = config.trailer_url || null;
    URL_PARTICIPANTS = config.participants_url || null;
    URL_FRAMES = config.frames_url || null;
    URL_REVIEWS = config.reviews_url || null;
    URL_REVIEWS_DETAIL = config.reviews_detail_url || null;

    let token = config.token;

    if (!token || !token.length) {
        console.log(Error(`KINOPOISK MISSING TOKEN`));
        return [];
    }

    if (typeof token === 'string') {
        TOKEN = [token];
    } else {
        TOKEN = token;
    }

    let res = await getFilmsByChunks({ kinopoiskIds, chunks })

    return res;
}


const getFilmsByChunks = async ({ kinopoiskIds, chunks = 0 }) => {
    let idsChunks;

    if (chunks > 0) {
        idsChunks = util.splitArr(kinopoiskIds, chunks);
    } else {
        idsChunks = [kinopoiskIds];
    }


    let promises = [];

    for (let i = 0; i < idsChunks.length; i++) {
        promises.push(getFilmsChunk({ ids: idsChunks[i] }));
    }

    let promisesRes = await Promise.all(promises);

    let res = [];

    for (let i = 0; i < promisesRes.length; i++) {
        if (promisesRes[i].length) {
            res = res.concat(promisesRes[i]);
        }
    }

    return res;
}


const getFilmsChunk = async ({ ids }) => {
    let res = [];

    for (let i = 0; i < ids.length; i++) {
        let data = await getSingleFilm({ id: ids[i] });
        res.push(data);
    }

    return res;
}


const getSingleFilm = async ({ id }) => {
    let res: ResFilm = {} as any;

    if (URL_INFO) {
        res.info = await sendRequest({ id, url: URL_INFO });
    }

    if (URL_TRAILER) {
        res.trailer = await sendRequest({ id, url: URL_TRAILER });
    }

    if (URL_PARTICIPANTS) {
        res.participants = await sendRequest({ id, url: URL_PARTICIPANTS });
    }

    if (URL_FRAMES) {
        res.frames = await sendRequest({ id, url: URL_FRAMES });
    }

    if (URL_REVIEWS && URL_REVIEWS_DETAIL) {
        res.reviews = await getReviews({ id, urlReviews: URL_REVIEWS, urlDetail: URL_REVIEWS_DETAIL });
    }

    res.kinopoisk_id = id;

    debugFile('kinopoisk', id, res);

    return res;
}


const getReviews = async ({ id, urlReviews, urlDetail }) => {
    return {};
}


const sendRequest = async ({ id, reviewId, url }: { id: string, reviewId?: string, url: string }) => {
    return new Promise(resolve => {
        let requestUrl = url;

        if (id) {
            requestUrl = requestUrl.replace(/\{id\}/, id);
        } else if (reviewId) {
            requestUrl = requestUrl.replace(/\{review_id\}/, reviewId);
        }

        axios.get(requestUrl, getAxiosConfig()).then(response => {
            let res = parseAxiosResponse(response);
            res = res ? res : {};
            console.log(`KINOPOISK SUCCESS REQUEST ${requestUrl}`);
            resolve(res);
        }).catch(err => {
            if (err.response && err.response.status) {
                console.log(Error(`KINOPOISK SEND REQUEST. RESPONSE STATUS [${err.response.status}]`));
            } else {
                console.log(Error(`KINOPOISK SEND REQUEST. RESPONSE STATUS [???]`));
                console.log(err.config.url);
            }
            resolve({});
        });
    });
}


const getToken = () => {
    return TOKEN[Math.floor(Math.random() * TOKEN.length)];
}


const getAxiosConfig = () => {
    let conf = {
        headers: { 'X-API-KEY': getToken() },
        timeout: 5000
    };
    return conf;
}


const parseAxiosResponse = response => {
    if (!response) return false;
    if (response.status !== 200) return false;
    if (!response.data) return false;
    return response.data;
}