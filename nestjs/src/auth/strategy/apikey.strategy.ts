import { PassportStrategy } from '@nestjs/passport';
import { HeaderAPIKeyStrategy } from 'passport-headerapikey';
import { AuthService } from '../auth.service';
import { Injectable, UnauthorizedException } from '@nestjs/common';

@Injectable()
export class ApiKeyStrategy extends PassportStrategy(HeaderAPIKeyStrategy, 'Authorization') {
    constructor(private authService: AuthService) {
        super(
            {header: 'Authorization', prefix: 'Bearer '},
            true,
            async (apiKey, done) => {
                if (this.authService.validateApiKey(apiKey)) {
                    done(null, true);
                }

                done(new UnauthorizedException(), null);
            }
        );
    }
}
