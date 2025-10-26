FROM node:21-alpine

RUN apk add --no-cache \
    curl \
    wget \
    build-base \
    python3 \
    pkgconfig \
    make \
    chromium \
    nss \
    freetype \
    harfbuzz \
    ttf-freefont

RUN apk add --no-cache ca-certificates && update-ca-certificates

ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

WORKDIR /app

COPY package*.json ./

ARG CACHEBUST=1

RUN npm install

COPY . .

RUN npm run build

CMD ["npm", "run", "start:prod"]
