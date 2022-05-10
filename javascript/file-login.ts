import dom from "../DOM";
import { ajax } from "../DOM/fn";
import { addFormError, parseServerResponse, ServerResponse } from "../parseServerResponse";
import { removeInputError } from "./validate";
import * as crypto from "crypto-browserify";
import * as buffer from "buffer";
import * as sshpk from "sshpk";

export const loginForm = () => {
    dom('.js-login-form', true).on('submit', async e => {
        e.preventDefault();

        let form = e.currentTarget as HTMLFormElement;

        if (form.classList.contains('loading')) {
            return;
        }

        form.classList.add('loading');

        clearForm(form);

        await login(e.currentTarget as HTMLFormElement);

        form.classList.remove('loading');

        return false;
    });
}

const login = async (form: HTMLFormElement) => {
    let sign = await getSign(form);
    
    if (!sign) {
        addFormError('Произошла серверная ошибка', form);
        return false;
    }

    let isValid = await verifySign(form, sign);
}


/**
 * - Валидирует секретную строку 
 */
const verifySign = async (form: HTMLFormElement, sign: string): Promise<boolean> => {
    let url = form.getAttribute('action');
    let data = new FormData(form);

    data.append('sign', sign);

    let res = await ajax({ url, data });
    let response = parseServerResponse(res.responseText, form) as ServerResponse;

    if (!response || response.status !== 'success') {
        return false;
    }

    return true;
}


const getSign = async (form: HTMLFormElement): Promise<string | false> => {
    let file = form.querySelector<HTMLInputElement>('input[type="file"]').files[0];

    if (!file) {
        return false;
    }

    let { pubKey, privKey } = await getKeysContent(file);

    if (!pubKey || !privKey) {
        return false;
    }

    let sign = await fetchSign(form, pubKey);

    if (!sign) {
        return false;
    }

    try {
        let strSign = atob(sign);
        let sshPrivKey = sshpk.parsePrivateKey(buffer.Buffer.from(privKey), 'openssh');
        let privateKeyPkcs8 = sshPrivKey.toBuffer('pkcs1');

        return crypto.privateDecrypt(
            {
                key: privateKeyPkcs8.toString('utf8'),
                padding: crypto.constants.RSA_PKCS1_PADDING
            },
            buffer.Buffer.from(strSign, 'ascii')
        ).toString();
    } catch (e) {
        return false;
    }
}


const fetchSign = async (form: HTMLFormElement, pubKey: string): Promise<false | string> => {
    let url = form.getAttribute('action');
    let data = new FormData(form);
    data.append('key-pub', btoa(pubKey));

    let res = await ajax({ url, data });

    let response = parseServerResponse(res.responseText, form);

    // @ts-ignore
    return response.sign ? response.sign : false;
}


const getKeysContent = async (file: File) => {
    let content = atob(await getSingleFileContent(file));

    let matches = content.match(/^(ssh-rsa.+)(-----BEGIN RSA PRIVATE KEY-----.+-----END RSA PRIVATE KEY-----)/s);

    return { pubKey: matches[1] || '', privKey: matches[2] || '' };
}


const getSingleFileContent = async (file: File): Promise<string> => {
    return new Promise(async resolve => {
        let reader = new FileReader();
        reader.onload = (theFile) => {
            resolve(theFile.target.result as string);
        }
        reader.readAsText(file);
    });
}


const clearForm = (form: HTMLFormElement) => {
    dom(form)
        .find(".js-server-response")
        .removeClass("error success")
        .html("");

    dom('.input', form).each(input => removeInputError(input));
}