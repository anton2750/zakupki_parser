import {Injectable} from '@nestjs/common';
import * as process from 'process';

@Injectable()
export class AuthService {
    validateApiKey(apiKey: string) {
        const apiKeys = [process.env.API_KEY];
        return apiKeys.find((key) => apiKey == key);
    }
}
