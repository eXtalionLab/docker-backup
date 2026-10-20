#!/bin/sh
set -e

# Run borg as the same uid/gid that owns the repositories on the host
BORG_UID="${BORG_UID:-1000}"
BORG_GID="${BORG_GID:-${BORG_UID}}"

if ! getent group borg > /dev/null; then
    addgroup -g "${BORG_GID}" borg
fi

if ! getent passwd borg > /dev/null; then
    adduser -D -H -h /home/borg -s /bin/sh -u "${BORG_UID}" -G borg borg
    # Account must be unlocked, otherwise sshd rejects public key login
    sed -i 's/^borg:!/borg:*/' /etc/shadow
    mkdir -p /home/borg
    chown borg:borg /home/borg
fi

mkdir -p /etc/ssh/keys

if [ ! -f /etc/ssh/keys/ssh_host_ed25519_key ]; then
    ssh-keygen -q -t ed25519 -N '' -f /etc/ssh/keys/ssh_host_ed25519_key
fi

if [ ! -f /etc/ssh/keys/ssh_host_rsa_key ]; then
    ssh-keygen -q -t rsa -b 4096 -N '' -f /etc/ssh/keys/ssh_host_rsa_key
fi

if [ ! -r /config/authorized_keys ]; then
    echo 'Mount authorized_keys file into /config/authorized_keys' >&2
    exit 1
fi

echo "borg $(borg --version | cut -d' ' -f2) serve as uid=${BORG_UID} gid=${BORG_GID}"

exec /usr/sbin/sshd -D -e
