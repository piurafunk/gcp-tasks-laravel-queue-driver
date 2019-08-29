FROM php:7.3-alpine

# Set up composer
COPY --from=composer:1.9 /usr/bin/composer /usr/bin/composer
ENV PATH=$PATH:/src/vendor/bin

# Install PHP extensions
RUN docker-php-ext-install -j `nproc` bcmath

# Install gRPC PECL module
ENV GRPC_VERSION=1.22.1
RUN apk add --no-cache autoconf g++ make zlib-dev && \
    cd /tmp && wget https://pecl.php.net/get/grpc-$GRPC_VERSION.tgz && tar xzf grpc-$GRPC_VERSION.tgz && cd grpc-$GRPC_VERSION && \
    phpize && ./configure && make -j `nproc` && make install && \
    cd .. && rm -rf grpc-$GRPC_VERSION grpc-$GRPC_VERSION.tgz && \
    apk del autoconf g++ make zlib-dev

# Install protobuf PECL module
ENV PROTOBUF_VERSION=3.9.1
RUN apk add --no-cache autoconf g++ make && \
    cd /tmp/ && wget https://pecl.php.net/get/protobuf-$PROTOBUF_VERSION.tgz && tar xzf protobuf-$PROTOBUF_VERSION.tgz && cd protobuf-$PROTOBUF_VERSION && \
    phpize && ./configure && make -j `nproc` && make install && \
    cd .. && rm -rf protobuf-$PROTOBUF_VERSION protobuf-$PROTOBUF_VERSION.tgz && \
    apk del autoconf g++ make

RUN echo "extension=grpc.so" > $PHP_INI_DIR/conf.d/pecl-grpc.ini && \
    echo "extension=protobuf.so" > $PHP_INI_DIR/conf.d/pecl-protobuf.ini
