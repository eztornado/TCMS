# Front del panel (React + Mantine): build de Vite servido por nginx.
# El proxy de /api y /storage al servicio `api` evita CORS en producción
# (todo sale por el mismo dominio, requisito de las cookies de Sanctum).

FROM node:22-alpine AS build
WORKDIR /app

COPY front/package.json front/package-lock.json* ./
RUN npm ci --no-fund --no-audit || npm install --no-fund --no-audit

COPY front/ ./
RUN npm run build

FROM nginx:1.27-alpine AS production
COPY docker/front/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s \
  CMD wget -q --spider http://localhost/ || exit 1
