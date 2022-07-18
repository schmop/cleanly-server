"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.whoami = void 0;
const node_fetch_1 = __importDefault(require("node-fetch"));
function whoami(token) {
    return __awaiter(this, void 0, void 0, function* () {
        const host = process.env.NODE_ENV === 'production' ? 'https://localhost' : 'http://localhost:8000';
        const response = yield (0, node_fetch_1.default)(`${host}/api/whoami`, {
            headers: {
                'Authorization': `Bearer ${token}`,
            }
        });
        if (response.status !== 200) {
            console.error('Could not retrieve user id from auth header!');
            return null;
        }
        return parseInt(yield response.text());
    });
}
exports.whoami = whoami;
