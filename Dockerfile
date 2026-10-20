# docker-backup client image. See https://github.com/eXtalionLab/docker-backup
FROM alpine:3.22

# Keep borg version in sync with server/Dockerfile
ARG BORG_VERSION=1.4

RUN apk add --no-cache \
        "borgbackup~${BORG_VERSION}" \
        bash \
        curl \
        docker-cli \
        openssh-client \
        supercronic \
        tzdata \
    && borg --version

COPY container/docker-backup /usr/local/bin/docker-backup

ENV BORG_BASE_DIR=/borg

VOLUME /borg
WORKDIR /backup

ENTRYPOINT ["docker-backup"]
CMD ["cron"]
