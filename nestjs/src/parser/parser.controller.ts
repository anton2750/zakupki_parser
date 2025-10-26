import { Controller, Get, UseGuards } from '@nestjs/common';
import { ParserService, ParserData } from './parser.service';
import { ApiKeyAuthGuard } from '../auth/guard/apikey-auth.guard';

@UseGuards(ApiKeyAuthGuard)
@Controller('parser')
export class ParserController {
    constructor(private readonly parserService: ParserService) {}

    @Get()
    async getParsers(): Promise<ParserData[]> {
        return await this.parserService.getData();
    }

    @Get('status')
    getCacheStatus() {
        return this.parserService.getCacheStatus();
    }
}
