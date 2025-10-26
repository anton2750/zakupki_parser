import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { ServeStaticModule } from '@nestjs/serve-static';
import { join } from 'path';
import { HealthController } from './health/health.controller';
import { AuthModule } from './auth/auth.module';
import { ScheduleModule } from '@nestjs/schedule';
import { ParserModule } from './parser/parser.module';

@Module({
    imports: [
        ConfigModule.forRoot(),
        ServeStaticModule.forRoot({
            rootPath: join(__dirname, '..', 'upload'),
            serveRoot: '/upload',
        }),
        AuthModule,
        ScheduleModule.forRoot(),
        ParserModule,
    ],
    controllers: [HealthController],
})
export class AppModule {}

