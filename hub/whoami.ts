import fetch from 'node-fetch';

export type Auth = string;
export type UserId = number;

const whoAmICache: Record<Auth, UserId> = {};

export async function whoami(token: Auth): Promise<null|UserId> {
    if (token in whoAmICache) {
        return whoAmICache[token];
    }
    const host = process.env.NODE_ENV === 'production' ? 'https://cleanly.schmoppo.de' : 'http://nginx:8000';
    try {
        const response = await fetch(`${host}/api/whoami`, {
            headers: {
                'Authorization': `Bearer ${token}`,
            }
        });
        if (response.status !== 200) {
            console.error('Could not retrieve user id from auth header!');
            return null;
        }
        const userId = parseInt(await response.text());
        whoAmICache[token] = userId;

        return userId;
    } catch (err) {
        console.error(err);
        return null;
    }
}
