import * as domfn from "./fn";
import type { Selector, Context, HTMLAnyElement, CustomEventSets } from "./types";



export class DomElement {

    private _el: NodeList | HTMLAnyElement[] | [];


    constructor(selector?: Selector, context?: Context, single?: boolean) {
        this._el = this._getElement(selector, context, single);
    }


    /**
     * - Находит элемент в коллекции по нидексу
     * - Если не передать индекс вернет массив элементов 
     */
    get(index?: number): HTMLAnyElement[] | HTMLAnyElement | undefined {
        if (typeof index === 'undefined') {
            let el = [];
            let i = 0;
            let total = this._el.length;
            while (i < total) {
                el.push(this._el[i]);
                i++;
            }
            return el as HTMLAnyElement[];
        }
        return this._el[index] ? this._el[index] as HTMLAnyElement : undefined;
    }


    /**
     * - Циклом проходится по всем элементам коллекции
     * @param fn коллбэк функция
     * @param breakOn индекс на которм надо остановить перебор элементов
     */
    each(fn: (el: HTMLAnyElement, i: number) => void, breakOn?: number): DomElement {
        domfn.each(this._el, (item, i) => {
            fn(item, i);
        }, breakOn);
        return this;
    }


    /**
     * - Находит элементы во всех элементах коллекции
     */
    find(selector: Selector, single?: boolean): DomElement {
        let res = [];

        this.each(item => {
            domfn.each(this._getElement(selector, item, single), findItem => {
                res.push(findItem);
            });
        });

        return new DomElement(res);
    }


    /**
     * - Находит первый элемент
     */
    first(): DomElement {
        return new DomElement(this.get(0));
    }


    /**
     * - Находит последний элемент
     */
    last(): DomElement {
        return new DomElement(this._el[this._el.length - 1] as HTMLAnyElement);
    }


    /**
     * - Добавляет классы
     */
    addClass(className: string): DomElement {
        return this.each(el => domfn.addClass(el, className));
    }


    /**
     * - Удаляет классы
     */
    removeClass(className: string): DomElement {
        return this.each(el => domfn.removeClass(el, className));
    }


    /**
     * - Проверяет наличие класса у первого элемента коллекции
     */
    hasClass(className: string): boolean {
        let el = this.get(0) as HTMLAnyElement;
        if (!el) return false;
        return el.classList.contains(className);
    }


    /**
     * - Добавляет/удаляет классы 
     */
    toggleClass(className: string): DomElement {
        return this.each(el => domfn.toggleClass(el, className));
    }


    /**
     * - Находит следующие элементы всех элементов коллекции
     */
    next(): DomElement {
        let res = [];
        this.each(el => res.push(domfn.next(el)));
        return new DomElement(res);
    }


    /**
     * - Находит предыдущие элементы всех элементов коллекции
     */
    prev(): DomElement {
        let res = [];
        this.each(el => res.push(domfn.prev(el)));
        return new DomElement(res);
    }


    /**
     * - Находит родительские элементы всех элементов коллекции
     */
    parent(): DomElement {
        let res = [];
        this.each(el => res.push(domfn.parent(el)));
        return new DomElement(res);
    }


    /**
     * - Удаляет из документа все элементы коллекции
     */
    remove() {
        this.each(el => domfn.remove(el));
    }


    /**
     * - Получае/Устанавливает значение атрибута 
     */
    attr(attr: string, value?: string): string | DomElement {
        // получаем значение атрибута
        if (typeof value === 'undefined') {
            let rValue: string;
            this.each(el => rValue = domfn.attr(el, attr), 1);
            return rValue;
        }
        // устанавливаем значение атрибута
        return this.each(el => domfn.attr(el, attr, value), 1);
    }


    /**
     * - если передан value, установит innerHTML всем элементам коллекции
     * - если не передан value, вернет innerHTML первого элемента коллекции
     */
    html(value?: string): string | DomElement {
        // получаем innerHTML первого элемента
        if (typeof value === 'undefined') {
            this.each(el => value = domfn.html(el), 1);
            return value;
        }
        // устанавливаем innerHTML всем элементам
        return this.each(el => domfn.html(el, value));
    }


    /**
     * - Добавляет прослушиватель события на все элементы коллекции
     * - Можно передать несколько событий разделенные пробелом
     *   например DomElement.on('mousedown mouseup', callback);
     */
    on(event: string, callback: EventListenerOrEventListenerObject, options?: AddEventListenerOptions) {
        let events = event.split(' ');
        return this.each(el => events.forEach(ev => {
            el.addEventListener(ev, callback, options);
        }));
    }


    /**
     * - Делегирует события на всех элементах коллекции
     * - Можно передать несколько событий разделенных пробелом
     */
    dispatch(event: string, sets?: CustomEventSets): DomElement {
        let events = event.split(' ');
        return this.each(el => events.forEach(ev => {
            domfn.dispatch(el, ev, sets);
        }));
    }


    /**
     * - Ищет родительские элементы всех элементов коллекции
     *   которые соответствуют переданному селектору
     */
    closest(selector: string): DomElement {
        if (!this.get(0)) {
            return new DomElement();
        }

        let search = document.querySelectorAll(selector);

        if (!search.length) {
            return new DomElement();
        }

        let res = [];

        this.each(el => {
            res.push(domfn.closest(el, selector, search));
        });

        res = res.filter(item => item && item.tagName);

        return new DomElement(res);
    }


    /**
     * - Получает значение первого элемента коллекции
     *   либо устанавливает значение всем элементам коллекции
     * - Если не передан value вернет значение инпута
     * 
     * @return {string} если найдено значение
     * @return {boolean} если если это checkbox или radio
     * @return {undefined} если нет элемента в коллекции или это не элемент формы
     * @return {DomElement} если установлено новое значение
     * 
     */
    val(value?: string): string | boolean | undefined | DomElement {
        let el = this.get(0) as HTMLInputElement;
        let isValid = false;

        if (el) {
            isValid = domfn.isFormField(el);
        }

        // получаем значение
        if (typeof value === 'undefined') {
            if (!isValid) {
                return undefined;
            }
            return domfn.val(el);
        }

        return this.each(item => domfn.val(item as HTMLInputElement, value));
    }


    /**
     * - Получает дочерние элементы всех элементов коллекции
     */
    childs(): DomElement {
        let res = [];
        this.each(item => {
            domfn.each(item.children, child => {
                res.push(child);
            });
        });
        return new DomElement(res);
    }


    append(append: HTMLAnyElement): DomElement {
        return this.each(el => el.appendChild(append));
    }


    private _getElement(selector?: Selector, context?: Context, single?: boolean): HTMLAnyElement[] | NodeList {
        // создается пустой экземпляр
        if (!selector) {
            return [];
        }

        // это html элемент
        if (selector instanceof HTMLElement && selector.tagName) {
            return [selector];
        }

        // это коллекция элементов
        if (selector instanceof NodeList) {
            return selector;
        }

        // это массив элементов
        if (selector instanceof Array) {
            return selector as HTMLAnyElement[];
        }

        // это объект данного класса
        if (selector instanceof DomElement) {
            return selector.get() as HTMLAnyElement[];
        }

        // это селектор. Запускаем поиск

        let realContext = this._getContext(context);

        if (!realContext) {
            return [];
        }

        if (single) {
            let item = realContext.querySelector(selector as string) as HTMLAnyElement;
            return item ? [item] : [];
        }

        return realContext.querySelectorAll(selector as string);
    }


    private _getContext(context?: Context): HTMLAnyElement | Document | undefined {
        if (context === undefined) {
            return document;
        }

        if (context instanceof HTMLElement) {
            return context;
        }

        if (context instanceof Document) {
            return context;
        }

        if (context instanceof DomElement) {
            return context.get(0) as HTMLAnyElement;
        }

        return document.querySelector(context as string) as HTMLAnyElement | undefined;
    }
}
