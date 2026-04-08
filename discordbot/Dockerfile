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
WORKDIR /app
COPY --from=build /app/publish .
ENTRYPOINT ["dotnet", "DailyNewsBot.dll"]
