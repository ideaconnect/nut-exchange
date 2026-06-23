FROM dunglas/frankenphp:builder AS builder
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy
RUN CGO_ENABLED=1 XCADDY_SETCAP=1 \
    CGO_CFLAGS=$(php-config --includes) \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build \
      --output /usr/local/bin/frankenphp \
      --with github.com/dunglas/frankenphp=./ \
      --with github.com/dunglas/frankenphp/caddy=./caddy/ \
      --with github.com/ideaconnect/nuts

FROM dunglas/frankenphp AS app
COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
