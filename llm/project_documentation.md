# Rodnik.today - Geo-monitoring Water Sources Platform

## Project Overview
Rodnik.today is a geo-monitoring web application for sharing user reports on the state of public water sources worldwide. The platform was launched on August 28, 2021, and serves as a community-driven resource for documenting and monitoring natural water sources across the globe.

## Core Principles
- **Easy access to open geo-information** - Making water source data freely accessible
- **Community-focused** - Leveraging voluntary sourcing of data from users worldwide
- **Public good** - Serving the common interest in water resource preservation
- **Global, independent, and non-commercial** - Operating without commercial interests to maintain data integrity

## System Functionality
The platform contains information about nearly one million water sources worldwide, all marked on an interactive map. Any internet user can submit reports on water sources, attach photos, and provide subjective water quality assessments. All reports are publicly visible, enabling anyone to access up-to-date field information on water sources of interest.

## Technical Architecture
- **Data Sources**: Primary geo-database from OpenStreetMap with plans to integrate other global and local open geodata sources
- **Data Collection**: Voluntary Geographic Information System (VGIS) approach for collecting real-time data
- **Data Layers**: Custom VGIS data layer maintained on top of static map data to track current states and history

## Technology Stack
- Laravel PHP framework
- Livewire 
- Tailwind CSS
- Alpine.js framework
- MySQL database 
- OpenLayers mapping library

## Core Functionality Guide

### Creating a New Water Source
Water sources in Rodnik.today can be created by users to expand the database with previously undocumented sources.

**Process:**
1. Navigate to the map interface
2. Click on "Add new water source" button
3. Position the marker at the precise location of the water source
4. Fill in the required details form

**Parameters for Water Source Creation:**
- **Geographical coordinates** (latitude, longitude) - Auto-captured from map position
- **Name** - Optional identifier for the water source
- **Type** - Select from options: spring, well, fountain, etc.
- **Intermittent** - Indicates if the source flows seasonally/intermittently
- **Initial state** - Current flow condition (running, dripping, dry)

**UI Features during Creation:**
- Interactive map positioning with precision controls
- Reverse geocoding to show nearby landmarks/locations
- Option to add an immediate first report
- Mobile-responsive interface for field data collection

### Adding a Report to a Water Source
Reports are user observations about the current state of a water source, providing temporal data on water quality and availability.

**Process:**
1. Find and select a water source on the map
2. Click "Add Report" button on the source's information panel
3. Complete the report form
4. Upload photos (optional)
5. Submit report

**Report Parameters:**
- **Visit date** - When the observation was made
- **Quality assessment** - Options: good, uncertain, bad
- **Flow state** - Options: running, dripping, dry, not found
- **Comments** - Text field for detailed observations
- **Photos** - Up to multiple photos with optional geolocation data
- **Source modifications** - Suggested changes to source data if needed

**UI Features for Reports:**
- Photo upload with preview
- Location verification
- Mobile-friendly interface for field reporting
- Option to report source not found or incorrectly positioned

### Editing Objects and Reports

**Editing Water Sources:**
1. Navigate to the water source on the map
2. Click "Edit Source" option (available to authenticated users)
3. Modify relevant fields:
   - Update location coordinates
   - Change name
   - Modify type
   - Update intermittent status
4. Submit changes with optional justification note

**Editing Reports:**
1. Navigate to your own report (via profile or source page)
2. Select "Edit Report" option
3. Modify report details:
   - Update quality assessment
   - Change flow state
   - Edit comments
   - Add/remove photos
4. Save changes

**Note on Editing:**
- Users can edit their own reports
- Water source edits may be subject to community verification
- Edit history is preserved for transparency
- Hidden reports/sources marked accordingly

### Data Utilization

**For End Users:**
- **Map Navigation** - Explore water sources on an interactive map
- **Filtering** - Filter sources by type, quality, recent activity, etc.
- **Research** - Access historical data on specific water sources
- **Planning** - Use for travel/hiking planning to locate water sources
- **Contribution** - Add to the global database of water knowledge

**For Researchers/Organizations:**
- **Data Analysis** - Track changes in water availability over time
- **Environmental Monitoring** - Observe patterns in water quality
- **Community Engagement** - Involve local communities in water resource monitoring
- **Conservation Planning** - Identify at-risk sources for protection efforts

**For Developers:**
- **Data Integration** - Potential API access for integrating water source data
- **Contribution** - Join the development team to enhance platform capabilities
- **Local Projects** - Adapt system functionality for specific monitoring campaigns

## Database Structure
The system uses a MySQL database with key tables including:
- `springs` - Core water source information
- `reports` - User observations with timestamps
- `photos` - Images attached to reports
- `users` - User account information
- `spring_revisions` - Historical record of changes to sources

## Project Evolution
Starting as a small initiative by programmers and outdoor enthusiasts from Russia, the project has evolved into an international effort. The system now contains thousands of water source reports from users across multiple countries. The core team is distributed across Finland, Spain, Russia, Armenia, and Turkey, with development primarily based in Helsinki.

## Contributing
The project welcomes contributors with interests in hydrology, GIS, sustainable development, and volunteered monitoring. The development team is open to collaboration with communities running water-source related projects.

## Reasoning Behind Rodnik.today
- **Water is Life** - Public water sources are vital for lifestyle, health, culture, recreation, and history
- **Volunteered Geo-data** - Community observations create invaluable and exclusive information resources
- **Perfect Timing** - Current technological capabilities and social awareness create an ideal environment for geo-monitoring platforms

## Key Improvements to Traditional GIS
- Interactive user interface
- User-editable data
- Multi-user collaboration
- Historical data preservation (retrospective view) 