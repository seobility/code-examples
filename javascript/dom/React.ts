const isEvent = (k, v) => k.startsWith("on") && typeof v === "function";
const eventName = (k) => k.substr(2).toLowerCase();
const isString = (s) => typeof s === "string";
const isFunction = (s) => typeof s === "function";

const React = (tag, props, ...children) => {
    if (isFunction(tag)) {
        return tag({ ...props, children });
    }

    const el = attrs(document.createElement(tag), props);

    children
        .reduce((acc, val) => acc.concat(val), [])
        .forEach((child) => {
            if (typeof child === "undefined") return;
            const node = !isString(child)
                ? child
                : document.createTextNode(child);
            el.appendChild(node);
        });

    return el;
};

const attrs = (el, props) => {
    if (!props) {
        return el;
    }

    for (let [k, val] of Object.entries(props)) {
        if (isEvent(k, val)) {
            el.addEventListener(eventName(k), val);
            continue;
        }

        if (k === "class" || k === "className") {
            if (!val) continue;
            el.setAttribute("class", val);
            // const classes = Array.isArray(val) ? val : [val];
            // console.log(el);
            // el.classList.add(...classes);
            continue;
        }

        if (k === "innerHTML") {
            el.innerHTML = val;
            continue;
        }

        el.setAttribute(k, val);
    }
    return el;
};

export default React;
