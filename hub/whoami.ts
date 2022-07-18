import fetch from 'node-fetch';

export async function whoami(token: string): Promise<null|number> {
    const host = process.env.NODE_ENV === 'production' ? 'https://localhost' : 'http://localhost:8000';
    const response = await fetch(`${host}/api/whoami`, {
        headers: {
            'Authorization': `Bearer ${token}`,
        }
    });
    if (response.status !== 200) {
        console.error('Could not retrieve user id from auth header!');
        return null;
    }

    return parseInt(await response.text());
}