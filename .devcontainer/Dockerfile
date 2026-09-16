FROM mcr.microsoft.com/devcontainers/php:1-8.2

# Remove broken third-party yarn repo from the base image so apt-get update succeeds
RUN rm -f /etc/apt/sources.list.d/yarn.list
