import type { DomElement } from "./DomElement";

export interface HTMLAnyElement extends HTMLElement {
    [key: string]: any
}

export type Selector = string | HTMLAnyElement | DomElement | HTMLAnyElement[] | DomElement[] | NodeList;

export type Context = undefined | string | HTMLAnyElement | Document | DomElement;

export type CustomEventSets = {
    bubbles?: boolean,
    cancelable?: boolean,
    detail?: any
};