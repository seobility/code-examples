import { DomElement } from "./DomElement";
import type { Selector, Context } from "./types";


function dom(selector: Selector, context?: Context | boolean, single = false): DomElement {
    let realContext: Context;

    if (typeof context === 'boolean') {
        single = context;
        realContext = undefined;
    } else {
        realContext = context;
    }

    return new DomElement(selector, realContext, single);
}


export default dom;