# Borg server (borg serve over ssh). See https://github.com/eXtalionLab/docker-backup
FROM alpine:3.22

# Keep borg version in sync with ../Dockerfile
ARG BORG_VERSION=1.4

RUN apk add --no-cache \
        "borgbackup~${BORG_VERSION}" \
        openssh-server \
    && borg --version

COPY sshd_config /etc/ssh/sshd_config
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 22
VOLUME /etc/ssh/keys

ENTRYPOINT ["entrypoint.sh"]
