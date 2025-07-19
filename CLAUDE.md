# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Brottsplatskartan is a Swedish crime mapping website that displays police incidents from the Swedish Police website in a user-friendly format with map visualization. The site aggregates and presents crime data, focusing on geographical representation and recent events.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Vue.js 2.6, Bootstrap 5
- **Build Tool**: Laravel Mix (Webpack wrapper)
- **Database**: MySQL with Redis caching
- **Maps**: Leaflet.js
- **Styling**: Sass/SCSS

## Development Commands

### Local Development
```bash
# Start development server
./artisan serve
# Visits http://localhost:8000

# Watch for asset changes during development
npm run watch

# Build assets for development
npm run dev

# Build assets for production
npm run production
```

### Data Import Commands
```bash
# Fetch police crime events
./artisan crimeevents:fetch

# Fetch TextTV news articles
./artisan texttv:fetch
```

### Package Management
```bash
# Update composer packages (note Redis requirement handling)
composer update <package-name> --ignore-platform-req=ext-redis

# Install npm dependencies
npm install
```

### Testing
```bash
# Run PHPUnit tests
./vendor/bin/phpunit

# Alternative test command
php artisan test
```

## Key Architecture Components

### Models
- `CrimeEvent` - Main crime incident data model
- `VMAAlert` - Emergency alert system data
- `Place` - Geographic location data
- `Locations` - City/area mappings
- `Dictionary` - Crime type categorization

### Controllers
- `StartController` - Homepage and main views
- `PlatsController` - Location-specific crime data
- `CityController` - City-specific pages
- `LanController` - County-level data
- `ApiController` - API endpoints for data access
- `VMAAlertsController` - Emergency alerts

### Key Features
- Real-time crime data aggregation from Swedish Police RSS feeds
- Interactive map visualization using Leaflet
- Geographic filtering by cities, counties, and specific locations
- Integration with TextTV for news content
- Mobile-responsive design with PWA capabilities

### Data Sources
- Swedish Police RSS feeds (https://polisen.se/Aktuellt/RSS/Lokala-RSS-floden/)
- TextTV news integration
- OpenStreetMap for geographic data

### Frontend Assets
- Main JS bundle: `resources/js/app.js` → `public/js/app.js`
- Styles: `resources/sass/app.scss` → `public/css/app.css`
- Vue components in `resources/views/components/`
- Map-specific JS: `public/js/events-map.js`

### Database Structure
Crime events are stored with:
- Geographic coordinates (lat/lng)
- Administrative area levels
- Parsed location names
- Crime categorization
- Timestamp data
- View tracking for popular content

### Caching Strategy
- Redis used for session storage and caching
- Database query caching for geographic lookups
- Asset versioning via Laravel Mix manifest