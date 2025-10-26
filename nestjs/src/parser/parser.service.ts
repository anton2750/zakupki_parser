import { Injectable, InternalServerErrorException } from '@nestjs/common';
import { Cron } from '@nestjs/schedule';
import * as puppeteer from 'puppeteer';
import * as fs from 'fs';
import * as path from 'path';
import * as XLSX from 'xlsx';

export interface ParserData {
    number: string | null;
    inn: string | null;
    ogrn: string | null;
    kpp: string | null;
    jur: string | null;
    type: string | null;
    court: string | null;
    case_number: string | null;
    date_issue: string | null;
    date_implement: string | null;
}

@Injectable()
export class ParserService {
    private cache: ParserData[] | null = null;
    private cacheLoadingPromise: Promise<void> | null = null;
    private lastCacheUpdate: Date | null = null;

    constructor() {
        // Запускаем загрузку кеша в фоне, не блокируя запуск приложения
        this.startBackgroundCacheLoading();
    }

    private startBackgroundCacheLoading() {
        console.log('Запускаем фоновую загрузку кеша...');
        this.cacheLoadingPromise = this.refreshCache()
            .then(() => {
                console.log('Попытка загрузить кеш в фоне завершена');
            })
            .catch((error) => {
                console.error('Ошибка при фоновой загрузке кеша:', error instanceof Error ? error.message : String(error));
                console.log('Кеш будет заполнен при первом запросе или по расписанию');
            });
    }

    async getData(): Promise<ParserData[]> {
        if (this.cache) {
            return this.cache;
        }

        // Если кеш еще загружается в фоне, ждем его завершения
        if (this.cacheLoadingPromise) {
            console.log('Кеш загружается в фоне, ждем завершения...');
            try {
                await this.cacheLoadingPromise;
                if (this.cache) {
                    return this.cache;
                }
            } catch (error) {
                console.log('Фоновая загрузка не удалась, загружаем кеш синхронно...');
            }
        }

        // если кеш пустой → заполняем синхронно
        console.log('Загружаем кеш синхронно...');
        this.cache = await this.fetchData();
        this.lastCacheUpdate = new Date();
        return this.cache;
    }

    getCacheStatus(): { isLoaded: boolean; recordCount: number; lastUpdate: Date | null } {
        return {
            isLoaded: this.cache !== null,
            recordCount: this.cache ? this.cache.length : 0,
            lastUpdate: this.lastCacheUpdate
        };
    }

    @Cron('0 * * * *') // каждый час в начале часа
    async refreshCache() {
        try {
            console.log('Обновляю кеш...');
            const startTime = Date.now();
            this.cache = await this.fetchData();
            this.lastCacheUpdate = new Date();
            const duration = Date.now() - startTime;
            console.log(`Кеш успешно обновлен за ${duration}мс. Записей: ${this.cache.length}`);
        } catch (err) {
            console.error('Ошибка при обновлении кеша:', err instanceof Error ? err.message : String(err));
        }
    }

    private async fetchData(): Promise<ParserData[]> {
        console.log('Начинаем снимать данные - fetchData');
        let browser: puppeteer.Browser | undefined;
        try {
            console.log('Запускаем Puppeteer браузер...');
            const launchStartTime = Date.now();

            browser = await puppeteer.launch({
                headless: true,
                executablePath:
                    process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium-browser',
                args: [
                    '--ignore-certificate-errors',
                    '--ignore-ssl-errors',
                    '--ignore-certificate-errors-spki-list',
                    '--allow-running-insecure-content',
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-blink-features=AutomationControlled',
                    '--disable-features=VizDisplayCompositor',
                    '--disable-web-security',
                    '--disable-features=TranslateUI',
                    '--disable-ipc-flooding-protection',
                    '--no-first-run',
                    '--no-default-browser-check',
                    '--disable-default-apps',
                    '--disable-popup-blocking',
                    '--disable-extensions',
                    '--disable-plugins',
                    '--disable-images',
                    '--disable-gpu',
                    '--disable-dev-shm-usage',
                    '--disable-background-timer-throttling',
                    '--disable-backgrounding-occluded-windows',
                    '--disable-renderer-backgrounding',
                    '--disable-field-trial-config',
                    '--disable-back-forward-cache',
                    '--disable-ipc-flooding-protection',
                    '--disable-extensions-http-throttling',
                    '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    '--proxy-bypass-list=<-loopback>'
                ],
            });

            const launchDuration = Date.now() - launchStartTime;
            console.log(`Puppeteer браузер успешно запущен за ${launchDuration}мс`);

            console.log('Инициализируем папку downloads');
            const downloadPath = path.resolve('./downloads');
            console.log(`Путь к папке downloads: ${downloadPath}`);

            // Создаем папку если её нет
            if (!fs.existsSync(downloadPath)) {
                console.log('Создаем папку downloads');
                fs.mkdirSync(downloadPath, { recursive: true });
            } else {
                console.log('Очищаем содержимое папки downloads');
                // Очищаем только содержимое папки, не удаляя саму папку
                const files = fs.readdirSync(downloadPath);
                for (const file of files) {
                    const filePath = path.join(downloadPath, file);
                    try {
                        const stat = fs.statSync(filePath);
                        if (stat.isDirectory()) {
                            fs.rmSync(filePath, { recursive: true, force: true });
                        } else {
                            fs.unlinkSync(filePath);
                        }
                        console.log(`Удален: ${file}`);
                    } catch (error) {
                        console.log(`Не удалось удалить ${file}:`, error instanceof Error ? error.message : String(error));
                    }
                }
            }
            console.log('Папка downloads готова к использованию');

            const page = await browser.newPage();

            console.log('SSL проверка сертификатов отключена');

            // Настраиваем загрузку файлов ПЕРВЫМ ДЕЛОМ
            const client = await page.target().createCDPSession();
            await client.send('Page.setDownloadBehavior', {
                behavior: 'allow',
                downloadPath,
            });
            // Дополнительно отключаем SSL через CDP
            await client.send('Security.setIgnoreCertificateErrors', { ignore: true});
            // Включаем события загрузки
            await client.send('Page.enable');
            await client.send('Network.enable');

            // Отслеживаем события загрузки
            let downloadStarted = false;
            client.on('Page.downloadWillBegin', (event) => {
                console.log('Началась загрузка файла:', event.suggestedFilename);
                downloadStarted = true;
            });

            client.on('Page.downloadProgress', (event) => {
                if (event.state === 'completed') {
                    console.log('Загрузка завершена:', event.guid);
                } else if (event.state === 'canceled') {
                    console.log('Загрузка отменена:', event.guid);
                } else {
                    console.log('Прогресс загрузки:', Math.round((event.receivedBytes / event.totalBytes) * 100) + '%');
                }
            });

            // Устанавливаем реалистичный User-Agent
            await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            // Устанавливаем реалистичный viewport
            await page.setViewport({ width: 1920, height: 1080 });

            // Устанавливаем дополнительные заголовки
            await page.setExtraHTTPHeaders({
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language': 'ru-RU,ru;q=0.9,en;q=0.8',
                'Accept-Encoding': 'gzip, deflate, br',
                'DNT': '1',
                'Connection': 'keep-alive',
                'Upgrade-Insecure-Requests': '1',
            });

            // Скрываем признаки автоматизации
            await page.evaluateOnNewDocument(() => {
                Object.defineProperty(navigator, 'webdriver', {
                    get: () => undefined,
                });

                Object.defineProperty(navigator, 'plugins', {
                    get: () => [1, 2, 3, 4, 5],
                });

                Object.defineProperty(navigator, 'languages', {
                    get: () => ['ru-RU', 'ru', 'en'],
                });

                (window as any).chrome = {
                    runtime: {},
                };
            });
            console.log('Открываем страницу минюста');

            // Устанавливаем таймауты
            page.setDefaultTimeout(60000);
            page.setDefaultNavigationTimeout(60000);

            // Пытаемся загрузить страницу с retry логикой
            let retryCount = 0;
            const maxRetries = 3;
            let pageLoaded = false;

            while (retryCount < maxRetries && !pageLoaded) {
                try {
                    await page.goto(
                        'https://zakupki.gov.ru/epz/main/public/document/view.html?sectionId=2369',
                        {
                            waitUntil: 'networkidle2',
                            timeout: 60000
                        },
                    );
                    pageLoaded = true;
                    console.log('Страница успешно загружена');
                } catch (error) {
                    retryCount++;
                    console.log(`Попытка ${retryCount} загрузки страницы не удалась:`, error instanceof Error ? error.message : String(error));
                    if (retryCount < maxRetries) {
                        console.log(`Повторная попытка через 5 секунд...`);
                        await new Promise(resolve => setTimeout(resolve, 5000));
                    } else {
                        throw error;
                    }
                }
            }

            // Ждем полного рендеринга страницы перед поиском ссылки
            console.log('Ждем полного рендеринга страницы...');
            await new Promise((resolve) => setTimeout(resolve, 5000)); // 5 секунд на рендеринг
            // Дополнительно ждем, пока все ресурсы загрузятся
            try {
                await page.waitForFunction(() => document.readyState === 'complete', { timeout: 10000 });
                console.log('Страница полностью загружена');
            } catch (error) {
                console.log('Таймаут ожидания загрузки страницы, продолжаем...');
            }
            // Пробуем разные селекторы для ссылки скачивания с учетом измненения названия ссылки
            const downloadSelectors = [
                'a[href*="downloadDocument.html"]',
                '.docs-title',
            ];
            let downloadLinkSelector = '';
            let selectorFound = false;
            retryCount = 0;
            // Пробуем найти ссылку с разными селекторами
            for (const selector of downloadSelectors) {
                try {
                    console.log(`Пробуем селектор: ${selector}`);
                    await page.waitForSelector(selector, { timeout: 10000 });
                    downloadLinkSelector = selector;
                    selectorFound = true;
                    console.log(`Ссылка для скачивания найдена с селектором: ${selector}`);
                    break;
                } catch (error) {
                    console.log(`Селектор ${selector} не найден:`, error instanceof Error ? error.message : String(error));
                }
            }
            if (!selectorFound) {
                // Если не нашли по селекторам, попробуем найти все ссылки на странице
                console.log('Ищем все ссылки на странице...');
                const allLinks = await page.$$eval('a', links =>
                    links.map(link => ({
                        href: link.href,
                        text: link.textContent?.trim(),
                        innerHTML: link.innerHTML
                    }))
                );
                console.log('Найденные ссылки:', allLinks.slice(0, 10)); // Показываем первые 10
                const downloadLink = allLinks.find(
                    (link) =>
                        link.href.includes('.xlsx') ||
                        link.text?.toLowerCase().includes('скачать') ||
                        link.text?.toLowerCase().includes('download') ||
                        link.innerHTML.includes('скачать') ||
                        link.innerHTML.includes('download'),
                );
                if (downloadLink) {
                    console.log('Найдена ссылка для скачивания:', downloadLink);
                    // Кликаем по найденной ссылке
                    await page.evaluate((href) => {
                        const link = document.querySelector(`a[href="${href}"]`);
                        if (link) {
                            (link as HTMLAnchorElement).click();
                        }
                    }, downloadLink.href);
                    console.log('Клик по ссылке для скачивания выполнен через evaluate');
                } else {
                    throw new Error('Не удалось найти ссылку для скачивания ни одним способом');
                }
            } else {
                // Валидируем ссылку перед кликом
                const linkElement = await page.$(downloadLinkSelector);
                if (!linkElement) {
                    throw new Error('Ссылка для скачивания не найдена на странице');
                }
                // Проверяем, что ссылка видима и кликабельна
                const isVisible = await linkElement.isIntersectingViewport();
                const isEnabled = await page.evaluate((selector) => {
                    const element = document.querySelector(selector);
                    return element && !element.hasAttribute('disabled');
                }, downloadLinkSelector);
                console.log(`Ссылка видима: ${isVisible}, активна: ${isEnabled}`);
                if (!isVisible) {
                    console.log('Ссылка не видна, прокручиваем к ней...');
                    await linkElement.scrollIntoView();
                    await new Promise((resolve) => setTimeout(resolve, 1000));
                }
                if (!isEnabled) {
                    throw new Error('Ссылка для скачивания неактивна');
                }
                // Получаем информацию о ссылке
                const linkInfo = await page.evaluate((selector) => {
                    const link = document.querySelector(selector) as HTMLAnchorElement;
                    if (link) {
                        return {
                            href: link.href,
                            text: link.textContent?.trim(),
                            disabled: link.hasAttribute('disabled'),
                            style: window.getComputedStyle(link).display
                        };
                    }
                    return null;
                }, downloadLinkSelector);
                console.log('Информация о ссылке:', linkInfo);
                // Дополнительное ожидание для стабилизации ссылки
                console.log('Ждем стабилизации ссылки перед кликом...');
                await new Promise((resolve) => setTimeout(resolve, 2000)); // 2 секунды на стабилизацию

                // Пробуем несколько способов клика
                let clickSuccess = false;
                const clickMethods = [
                    () => page.click(downloadLinkSelector),
                    () => linkElement.click(),
                    () =>
                        page.evaluate((selector) => {
                            const link = document.querySelector(
                                selector,
                            ) as HTMLAnchorElement;
                            if (link) {
                                link.click();
                                return true;
                            }
                            return false;
                        }, downloadLinkSelector),
                    () =>
                        page.evaluate((selector) => {
                            const link = document.querySelector(selector);
                            if (link) {
                                const event = new MouseEvent('click', {
                                    view: window,
                                    bubbles: true,
                                    cancelable: true,
                                });
                                link.dispatchEvent(event);
                                return true;
                            }
                            return false;
                        }, downloadLinkSelector),
                ];

                for (let i = 0; i < clickMethods.length; i++) {
                    try {
                        console.log(`Попытка клика методом ${i + 1}...`);
                        await clickMethods[i]();
                        clickSuccess = true;
                        console.log(`Клик по ссылке выполнен методом ${i + 1}`);
                        break;
                    } catch (error) {
                        console.log(
                            `Метод ${i + 1} не сработал:`,
                            error instanceof Error ? error.message : String(error),
                        );
                        if (i < clickMethods.length - 1) {
                            console.log('Ждем перед следующей попыткой...');
                            await new Promise((resolve) => setTimeout(resolve, 2000)); // Увеличили до 2 секунд
                        }
                    }
                }
                if (!clickSuccess) {
                    throw new Error('Не удалось кликнуть по ссылке ни одним методом');
                }
            }
            // Ждем немного после клика
            await new Promise((resolve) => setTimeout(resolve, 3000));

            let downloadedFile = '';
            const maxWaitTimeMs = 120000; // Увеличили до 2 минут
            const pollIntervalMs = 2000; // Увеличили интервал до 2 секунд
            let elapsedTimeMs = 0;
            console.log('Ожидаем скачивания файла...');
            // Дополнительная проверка - ждем начала загрузки
            let downloadStartWaitTime = 0;
            const maxDownloadStartWait = 5000; // 5 секунд на начало загрузки
            while (!downloadStarted && downloadStartWaitTime < maxDownloadStartWait) {
                await new Promise((resolve) => setTimeout(resolve, 1000));
                downloadStartWaitTime += 1000;
                if (downloadStartWaitTime % 1000 === 0) {
                    console.log(`Ждем начала загрузки... ${downloadStartWaitTime / 1000}с`);
                }
            }
            if (!downloadStarted) {
                console.log('Загрузка не началась в течение 5 секунд, продолжаем ожидание файлов...');
            } else {
                console.log('Загрузка началась, ожидаем завершения...');
            }

            while (elapsedTimeMs < maxWaitTimeMs) {
                try {
                    const files = fs.readdirSync(downloadPath);
                    // Ищем любые файлы, включая частично скачанные
                    const allFiles = files.filter(file => file.includes('.xlsx') || file.includes('.crdownload'));

                    if (allFiles.length > 0) {
                        console.log(`Файлы в папке downloads: ${files.join(', ')}`);
                        console.log(`Найдены файлы Excel: ${allFiles.join(', ')}`);
                    }
                    const xlsxFile = files.find(
                        (file) => file.endsWith('.xlsx') && !file.endsWith('.crdownload'),
                    );
                    if (xlsxFile) {
                        downloadedFile = path.join(downloadPath, xlsxFile);
                        console.log(`Файл найден: ${xlsxFile}`);
                        // Проверяем, что файл полностью скачался
                        let fileSize = 0;
                        let stableSizeCount = 0;
                        while (stableSizeCount < 3) {
                            // Ждем 3 проверки подряд с одинаковым размером
                            const currentSize = fs.statSync(downloadedFile).size;
                            if (currentSize === fileSize && currentSize > 0) {
                                stableSizeCount++;
                            } else {
                                stableSizeCount = 0;
                                fileSize = currentSize;
                            }
                            console.log(`Проверка размера файла: ${currentSize} байт (стабильных проверок: ${stableSizeCount})`);
                            await new Promise((resolve) => setTimeout(resolve, 1000));
                        }
                        console.log(`Файл полностью скачан, размер: ${fileSize} байт`);
                        break;
                    }
                    // Показываем прогресс каждые 10 секунд
                    if (elapsedTimeMs % 10000 === 0 && elapsedTimeMs > 0) {
                        console.log(`Ожидание скачивания... ${elapsedTimeMs / 1000}с`);
                        if (files.length > 0) {
                            console.log(`Текущие файлы: ${files.join(', ')}`);
                        }
                        if (!downloadStarted) {
                            console.log('Загрузка еще не началась, возможно проблема с ссылкой');
                        }
                    }
                } catch (error) {
                    console.log('Ошибка при проверке файлов:', error instanceof Error ? error.message : String(error));
                }
                await new Promise((resolve) => setTimeout(resolve, pollIntervalMs));
                elapsedTimeMs += pollIntervalMs;
            }

            if (!downloadedFile) {
                console.log('Файл не скачался, пробуем повторный клик...');
                // Попробуем еще раз кликнуть по ссылке
                try {
                    const linkElement = await page.$(downloadLinkSelector);
                    if (linkElement) {
                        console.log('Повторный клик по ссылке...');
                        await linkElement.click();
                        await new Promise((resolve) => setTimeout(resolve, 5000));
                        // Проверяем еще раз
                        const files = fs.readdirSync(downloadPath);
                        const xlsxFile = files.find(
                            (file) => file.endsWith('.xlsx') && !file.endsWith('.crdownload'),
                        );
                        if (xlsxFile) {
                            downloadedFile = path.join(downloadPath, xlsxFile);
                            console.log(`Файл найден после повторного клика: ${xlsxFile}`);
                        }
                    }
                } catch (retryError) {
                    console.log('Повторный клик не удался:', retryError instanceof Error ? retryError.message : String(retryError));
                }
                if (!downloadedFile) {
                    // Попробуем сделать скриншот для диагностики
                    try {
                        const screenshotPath = path.join(downloadPath, 'debug-screenshot.png') as `${string}.png`;
                        await page.screenshot({ path: screenshotPath, fullPage: true });
                        console.log(`Скриншот сохранен: ${screenshotPath}`);
                    } catch (screenshotError) {
                        console.log('Не удалось сделать скриншот:', screenshotError instanceof Error ? screenshotError.message : String(screenshotError));
                    }
                    // Получим HTML страницы для анализа
                    try {
                        const pageContent = await page.content();
                        const htmlPath = path.join(downloadPath, 'debug-page.html');
                        fs.writeFileSync(htmlPath, pageContent);
                        console.log(`HTML страницы сохранен: ${htmlPath}`);
                    } catch (htmlError) {
                        console.log('Не удалось сохранить HTML:', htmlError instanceof Error ? htmlError.message : String(htmlError));
                    }

                    throw new Error('Не удалось скачать XLSX файл даже после повторной попытки');
                }
            }

            await browser.close();

            console.log('Готовимся читать файл XLSX');
            const workbook = XLSX.readFile(downloadedFile);
            const sheetName = workbook.SheetNames[0];
            const sheet = workbook.Sheets[sheetName];
            const dataAsArray = XLSX.utils.sheet_to_json(sheet, { header: 1 });

            const finalData = dataAsArray
                .map((item: Record<string, any>): ParserData => {
                    const newItem: ParserData = {
                        number: null,
                        inn: null,
                        ogrn: null,
                        kpp: null,
                        jur: null,
                        type: null,
                        court: null,
                        case_number: null,
                        date_issue: null,
                        date_implement: null
                    };

                    newItem['number'] = item[0];
                    newItem['inn'] = item[1];
                    newItem['ogrn'] = item[2];
                    newItem['kpp'] = item[3];
                    newItem['jur'] = item[4];
                    newItem['type'] = item[5];
                    newItem['court'] = item[6];
                    newItem['case_number'] = item[7];
                    newItem['date_issue'] = item[8];
                    newItem['date_implement'] = item[9];

                    return newItem;
                })
                .filter((item: ParserData) => item.number);

            // Очищаем папку downloads после обработки файла
            console.log('Очищаем папку downloads после обработки файла');
            try {
                const files = fs.readdirSync(downloadPath);
                for (const file of files) {
                    const filePath = path.join(downloadPath, file);
                    try {
                        const stat = fs.statSync(filePath);
                        if (stat.isDirectory()) {
                            fs.rmSync(filePath, { recursive: true, force: true });
                        } else {
                            fs.unlinkSync(filePath);
                        }
                        console.log(`Удален: ${file}`);
                    } catch (error) {
                        console.log(`Не удалось удалить ${file}:`, error instanceof Error ? error.message : String(error));
                    }
                }
                console.log('Папка downloads очищена');
            } catch (error) {
                console.log('Ошибка при очистке папки downloads:', error instanceof Error ? error.message : String(error));
            }

            return finalData;
        } catch (err) {
            console.error('Критическая ошибка в fetchData:', err instanceof Error ? err.message : String(err));

            if (browser) {
                try {
                    console.log('Закрываем браузер...');
                    await browser.close();
                    console.log('Браузер успешно закрыт');
                } catch (closeError) {
                    console.error('Ошибка при закрытии браузера:', closeError instanceof Error ? closeError.message : String(closeError));
                }
            }

            const errorMessage = err instanceof Error ? err.message : String(err);
            console.error('Итоговая ошибка:', errorMessage);
            console.error('Stack trace:', err instanceof Error ? err.stack : 'No stack trace available');

            throw new InternalServerErrorException(`Ошибка при получении данных: ${errorMessage}`);
        }
    }
}