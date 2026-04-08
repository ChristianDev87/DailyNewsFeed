# Stage 1: Build
FROM mcr.microsoft.com/dotnet/sdk:9.0 AS build
WORKDIR /src
COPY DailyNewsBot.sln .
COPY DailyNewsBot/ ./DailyNewsBot/
COPY DailyNewsBot.Tests/ ./DailyNewsBot.Tests/
RUN dotnet restore
RUN dotnet publish DailyNewsBot -c Release -o /app/publish

# Stage 2: Runtime (kein SDK im finalen Image)
FROM mcr.microsoft.com/dotnet/runtime:9.0

# Nicht-Root-User für Log-Ownership auf dem Host-Volume
# APP_UID/APP_GID muss mit dem Service-User auf dem Host übereinstimmen (z.B. dailynews)
ARG APP_UID=1000
ARG APP_GID=1000
RUN addgroup --gid ${APP_GID} appgroup && \
    adduser --uid ${APP_UID} --ingroup appgroup --no-create-home --disabled-password appuser 2>/dev/null || true

WORKDIR /app
COPY --from=build /app/publish .
USER ${APP_UID}
ENTRYPOINT ["dotnet", "DailyNewsBot.dll"]
