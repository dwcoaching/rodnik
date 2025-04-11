# About

Table of contents:
- [Concept](#concept) 
- [Project Overview](#project-overview)
- [Functionality](#functionality)
- [Core Functionality Guide](#core-functionality-guide)
- [Architecture](#architecture)
- [Progress](#progress)
- [Technology Stack](#technology-stack)
- [Database Structure](#database-structure)
- [The Team](#the-team)
- [Why Are We Doing This?](#why-are-we-doing-this)
- [Contributing](#contributing)
- [Copyright and license](#copyright-and-license)
- [Join Our Community!](#join-our-community)

**Rodnik.today is a social layer on top of OpenStreetMap for exploring and sharing information about public water sources worldwide**

Rodnik.today uses OpenStreetMap as the source of data. Use it to explore public water sources or plan water supply during your long trips. Share local knowledge and up-to-date information with other community enthusiasts.

Verified information gets reported back to OpenStreetMap and improves quality of maps all over the world.

<a name="concept"></a>
## [Concept](#concept)

Rodnik.today is based on the following principles:

- Easy access to open geo-information - Making water source data freely accessible
- Focused on Community and voluntary sourcing of data from users worldwide
- Concern for the public good - Serving the common interest in water resource preservation
- Globality, independence and non-commercial approach - Operating without commercial interests to maintain data integrity

<a name="project-overview"></a>
## [Project Overview](#project-overview)

Rodnik.today is a geo-monitoring web application for sharing user reports on the state of public water sources worldwide. The platform was launched on August 28, 2021, and serves as a community-driven resource for documenting and monitoring natural water sources across the globe.

<a name="functionality"></a>
## [Functionality](#functionality)

The System contains information about almost a million water sources around the world. Objects are marked on the map. Any Internet user can submit a report on the current state of an arbitrary object, attach several photos and subjectively assess water quality. All reports uploaded by the user are visible to all, so anybody can get the latest field information on the object of their interest.

The platform provides an interactive map interface where users can explore water sources worldwide, filter them by various attributes, and access historical data on specific water sources.

<a name="core-functionality-guide"></a>
## [Core Functionality Guide](#core-functionality-guide)

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

### Editing Objects and Reports

**Editing Water Sources:**
1. Navigate to the water source on the map
2. Click "Edit Source" option (available to authenticated users)
3. Modify relevant fields (location coordinates, name, type, intermittent status)
4. Submit changes with optional justification note

**Editing Reports:**
1. Navigate to your own report (via profile or source page)
2. Select "Edit Report" option
3. Modify report details (quality assessment, flow state, comments, photos)
4. Save changes

<a name="architecture"></a>
## [Architecture](#architecture)

Rodnik.today implements a user interface to geo-data from its internal and third-party open resources. Currently, the global OpenStreetMap project is used as the geo-database. In future, it is planned to integrate with other global and local open sources of geodata.

The Voluntary GIS (VGIS) approach is used to collect live day-by-day data from the community. The system maintains its own VGIS data layer on top of the static map data to keep track of the current state and the retrospective of water objects.

**Key Components:**
- **Data Sources**: Primary geo-database from OpenStreetMap with plans to integrate other global and local open geodata sources
- **Data Collection**: Voluntary Geographic Information System (VGIS) approach for collecting real-time data
- **Data Layers**: Custom VGIS data layer maintained on top of static map data to track current states and history

**Key Improvements to Traditional GIS:**
- Interactive user interface
- User-editable data
- Multi-user collaboration
- Historical data preservation (retrospective view)

<a name="progress"></a>
## [Progress](#progress)

More than a year have passed since the release date of the trial (v.0.1) version of the system. Now there are over 30 registered users in our Community. These users have already entered almost 3000 water source reports in Spain, Russia, Andorra, Italy, Israel, Greece, Turkey, Finland, South Korea, Japan, Northern Ireland, Latvia, Belarus, Kazakhstan, France, Slovenia, Hungary, Azerbaijan, Serbia, Montenegro, and more.

With 0.1 version the proof of concept of the System has been completed. Version 1.0 is currently under development.

<a name="technology-stack"></a>
## [Technology Stack](#technology-stack)

- Laravel PHP framework
- Livewire
- Tailwind CSS
- Alpine.js framework
- MySQL database
- OpenLayers mapping library

<a name="database-structure"></a>
## [Database Structure](#database-structure)

The system uses a MySQL database with key tables including:
- `springs` - Core water source information
- `reports` - User observations with timestamps
- `photos` - Images attached to reports
- `users` - User account information
- `spring_revisions` - Historical record of changes to sources

<a name="the-team"></a>
## [The Team](#the-team)

The Project was started by a small group of programmers and outdoor enthusiasts from Russia. Very soon a community of people interested in the problem of public water sources rallied around the idea. The project has become international with development centers in Finland, Spain, Russia, Armenia, and Turkey, with software development based particularly in Helsinki.

<a name="why-are-we-doing-this"></a>
## [Why Are We Doing This?](#why-are-we-doing-this)

**WATER IS LIFE**

Public water sources hold tremendous significance in our lives, impacting our lifestyle, health, culture, recreation, history, and entertainment. Despite their uniqueness across different geographies, they possess universal importance. By accumulating global knowledge about these vital resources and providing easy access to this information, we contribute to a significant public good.

**Reasoning Behind Rodnik.today:**
- **Water is Life** - Public water sources are vital for lifestyle, health, culture, recreation, and history
- **Volunteered Geo-data** - Community observations create invaluable and exclusive information resources
- **Perfect Timing** - Current technological capabilities and social awareness create an ideal environment for geo-monitoring platforms

<a name="contributing"></a>
## [Contributing](#contributing)

The project welcomes contributors with interests in hydrology, GIS, sustainable development, and volunteered monitoring. The development team is open to collaboration with communities running water-source related projects.

<a name="copyright-and-license"></a>
## [Copyright and license](#copyright-and-license)

Rodnik.today is open data, licensed under the [Open Data Commons Open Database License](https://www.openstreetmap.org/copyright).

<a name="join-our-community"></a>
## [Join Our Community!](#join-our-community)

Rodnik.today is created by a community of outdoor enthusiasts. The project is non-profit and non-commercial. We love nature and we want to make the information accessible to everyone. **You too? Please, join our community!**

[Learn more about Rodnik.today](https://docs.google.com/document/d/173TpVT7EQCEVaLyL3uB9dsjSwYiSzZP-jeKSuRPVwfM/edit) *(Google document, work in progress)*.

- [Telegram Chat](https://t.me/rodnik_today)

- [Notifications Channel](https://t.me/rodniktoday) 






