# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Rodnik.today is a geo-monitoring web application for sharing user reports on public water sources worldwide. It's built with Laravel and uses OpenStreetMap data integration with a community-driven reporting system.

## Commands

### Development
- `npm run dev` - Start Vite development server for frontend assets
- `npm run build` - Build production frontend assets
- `php artisan serve` - Start Laravel development server

### Testing
- `php artisan test` - Run tests using Pest (configured in phpunit.xml)
- Test framework: Pest PHP with PHPUnit as the underlying test runner

### Database
- `php artisan migrate` - Run database migrations
- `php artisan db:seed` - Seed database with sample data

### Artisan Commands (Console/Commands)
The project includes many custom Artisan commands for data processing:
- `php artisan overpass:*` - OpenStreetMap data import/processing commands
- `php artisan spring:*` - Water source aggregation and tile generation commands
- `php artisan photos:get-sizes` - Process photo metadata

## Architecture

### Core Domain Models
- **Spring** (`app/Models/Spring.php`) - Core water source entity with coordinates, type, seasonal info
- **Report** (`app/Models/Report.php`) - User observations about water sources with quality/state assessments
- **Photo** (`app/Models/Photo.php`) - Images attached to reports
- **User** (`app/Models/User.php`) - User accounts with rating system
- **SpringRevision** - Tracks changes to water sources over time

### Action Pattern
All business logic follows a strict Action pattern (`docs/1.0/development/action-pattern.md`):
- Actions are invokable classes in `app/Actions/` with no subfolders
- Structure: `authorize()` → `validate()` → `execute()`
- Used primarily by Livewire components for data mutations
- Actions handle authorization via Laravel Policies/Gates
- Actions use Laravel validation with custom rules

### Frontend Architecture
- **Livewire 3** - Main reactive frontend framework
- **Alpine.js** - Client-side interactivity
- **Tailwind CSS** + **DaisyUI** - Styling
- **OpenLayers** - Interactive mapping (`resources/js/`)
- **Vite** - Asset bundling

### Data Integration
- **OpenStreetMap Integration** - Imports water source data via Overpass API
- **Background Jobs** - Queue-based processing for data imports and notifications
- **Tile System** - Custom tile generation for map performance (`app/Models/SpringTile.php`)

### Key Services
- **StatisticsService** (`app/Library/StatisticsService.php`) - Caching and stats calculations
- **Overpass** (`app/Library/Overpass.php`) - OSM data fetching
- **SpringsGeoJSON** (`app/Library/SpringsGeoJSON.php`) - GeoJSON output formatting

### Authentication & Authorization
- **Laravel Jetstream** - User management with Fortify
- **Policies** - Role-based access control for springs, reports, photos
- User rating system based on contributions

### Admin Interface
- **Filament** - Admin panel for managing data and background jobs

## Key Patterns

### Water Source Types
Predefined types in `Spring::TYPES`: Spring, Water well, Water tap, Drinking water source, Fountain, Water source

### Report Quality/State System
- Quality: good, uncertain, bad
- State: running, dripping, dry, not found

### OSM Tag Parsing
Springs have complex logic for parsing OpenStreetMap tags to determine type, name, and seasonal status

### Coordinate System
Uses decimal degrees (latitude/longitude) with custom validation rules

### File Organization
- Routes: `routes/web.php` (main), `routes/api/v1.php` (API)
- Views: Blade templates in `resources/views/` + Livewire components
- Frontend JS: `resources/js/` with OpenLayers mapping setup
- Tests: Pest framework in `tests/Feature/` and `tests/Unit/`

## Development Guidelines

### Code Style
- Follow Laravel conventions
- Use Action pattern for all business logic
- Test Actions thoroughly (validation, authorization, execution)
- Maintain clean separation between OSM data and user-generated data

### Database Considerations
- Uses MySQL with spatial indexing for coordinates
- Heavy use of background jobs for data processing
- Caching strategy for statistics and tile generation

### Geographic Data
- Coordinate validation via custom rules (`app/Rules/LatitudeRule.php`, `app/Rules/LongitudeRule.php`)
- Tile invalidation system for map updates
- GeoJSON export capabilities