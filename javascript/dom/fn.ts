import type { HTMLAnyElement, CustomEventSets } from "./types";



export const addClass = (el: HTMLAnyElement, className: string) => {
    className.split(' ').forEach(name => {
        if (!el.classList.contains(name)) {
            el.classList.add(name);
        }
    });
}


export const removeClass = (el: HTMLAnyElement, className: string) => {
    className.split(' ').forEach(name => {
        if (el.classList.contains(name)) {
            el.classList.remove(name);
        }
    });
}


export const hasClass = (el: HTMLAnyElement, className: string): boolean => {
    return el.classList.contains(className);
}


export const toggleClass = (el: HTMLAnyElement, className: string) => {
    className.split(' ').forEach(name => {
        if (el.classList.contains(name)) {
            el.classList.remove(name);
        } else {
            el.classList.add(name);
        }
    });
}


export const next = (el: HTMLAnyElement): HTMLAnyElement | null => {
    let next = el.nextSibling;
    while (next && next.nodeType !== 1) {
        next = next.nextSibling;
    }
    return next as HTMLAnyElement | null;
}


export const prev = (el: HTMLAnyElement): HTMLAnyElement | null => {
    let prev = el.previousSibling;
    while (prev && prev.nodeType !== 1) {
        prev = prev.previousSibling;
    }
    return prev as HTMLAnyElement | null;
}


export const parent = (el: HTMLAnyElement): HTMLAnyElement => {
    return el.parentElement;
}


export const remove = (el: HTMLAnyElement) => {
    el.parentNode.removeChild(el);
}


export const attr = (el: HTMLAnyElement, attr?: string, value?: string) => {
    if (typeof value === 'undefined') {
        return el.getAttribute(attr);
    }
    el.setAttribute(attr, value);
}


export const html = (el: HTMLAnyElement, value?: string) => {
    if (typeof value === 'undefined') {
        return el.innerHTML;
    }
    el.innerHTML = value;
}


export const dispatch = (el: HTMLAnyElement, ev: string, sets?: CustomEventSets) => {
    let event = new CustomEvent(ev, {
        bubbles: true, cancelable: true, detail: undefined, ...sets
    });
    el.dispatchEvent(event);
}



export const closest = (el: HTMLAnyElement, selector: string, search?: NodeList): HTMLAnyElement | null => {
    let realSearch: NodeList;

    if (!search) {
        realSearch = document.querySelectorAll(selector);
    } else {
        realSearch = search;
    }

    let total = search.length;
    if (!total) return null;

    let parent = el.parentElement;

    while (!parent || !parent.tagName || !Array.prototype.includes.call(realSearch, parent)) {
        if (parent === null) {
            break;
        }
        parent = parent.parentElement;
    }

    return parent;
}



export const val = (el: HTMLInputElement, value?: string): string | boolean => {
    if (typeof value === 'undefined') {
        if (el.type === 'checkbox' || el.type === 'radio') {
            return el.checked;
        }
        return el.value;
    }
    if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = !!value;
        return;
    }
    el.value = value;
}


export const isFormField = (el: HTMLAnyElement): boolean => {
    return /^(?:input|select|textarea|button)$/i.test(el.nodeName);
}


/**
 * - Оптимизированно переберает массив
 */
export const each = (
    arr: any[] | NodeList | HTMLCollection,
    fn: (item: any, i: number, arr: any[] | NodeList | HTMLCollection) => void, breakOn?: number
) => {
    let total = arr.length;
    let i = 0;

    while (i < total) {
        fn(arr[i], i, arr);
        i++;
        if (i === breakOn) {
            break;
        }
    }
}


type AjaxParams = {
    url: string,
    data?: any,
    xhrEvents?: any,
    getXhr?: boolean,
    success?: (target: XMLHttpRequestEventTarget, req: XMLHttpRequest) => void,
    error?: (target: XMLHttpRequestEventTarget, req: XMLHttpRequest) => void,
    method?: string,
    headers?: any
}

export const ajax = async ({ url, data, xhrEvents, getXhr, success, error, method, headers }: AjaxParams): Promise<XMLHttpRequest> => {
    return new Promise(async (resolve: any, reject) => {
        data = getRequestData(data);
        method = getRequestMethod(method, data);

        let xhr = new XMLHttpRequest;
        xhr.open(method, url, true);

        setRequestHeader(xhr, headers);

        if (xhrEvents) {
            for (let event in xhrEvents) {
                if (event === 'loadend') continue;
                xhr.addEventListener(event, xhrEvents[event]);
            }
        }

        xhr.addEventListener('loadend', (res: any) => {
            if (res.target.status !== 200) {
                error && error(res.target, res);
            } else {
                success && success(res.target, res);
                !getXhr && resolve(res.target, res);
            }
        });

        xhr.send(data);

        if (getXhr) {
            resolve(xhr);
        }
    });
}


const getRequestData = (data) => {
    if (!data) return null;

    let type = typeof data;

    if (type === 'string') return data;
    if (type !== 'object') return null;
    if (data instanceof FormData) return data;

    let requestData = new FormData();

    for (let key in data) {
        requestData.append(key, data[key]);
    }

    return requestData;
}


const getRequestMethod = (method, data) => {
    if (method) return method;
    return data ? 'post' : 'get';
}


const setRequestHeader = (xhr, headers) => {
    if (!headers) return;
    for (let key in headers) {
        xhr.setRequestHeader(key, headers[key]);
    }
}