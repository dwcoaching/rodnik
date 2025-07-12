# Water Source Types

> **Note:** Original document [here](https://docs.google.com/spreadsheets/d/1sDnIOWgyEtAAMGeFpSk0KXX2Qfu8A3PpZNFN_MxcWN0/edit?gid=0#gid=0).

- [Natural Water Sources](#natural-water-sources)
- [Water Collection Facilities](#water-collection-facilities)
- [Water Access Facilities](#water-access-facilities)
- [Original Table Format](#original-table-format)

This document categorizes different types of water sources, their descriptions, and corresponding OSM tag mappings where applicable.

## Natural Water Sources

### Natural Groundwater Outlet (Выход грунтовых вод)
- **Description:** A location where groundwater naturally emerges to the surface. May not have convenient water collection facilities. Could potentially be developed into a spring, well, or other water collection point.
- **Type:** Natural object
- **OSM Tag:** `natural = spring`

### Easy Water Access Point (Подход к воде)
- **Description:** A location or man-made object that provides convenient access to water from a natural reservoir or watercourse (e.g., boardwalks on a swampy shore, access to a hard-to-reach riverbed, where a trail crosses a stream).
- **Type:** Natural object, Water access facility
- **OSM Tag:** None defined

## Water Collection Facilities

### Water Spring (Родник)
- **Description:** A man-made structure for collecting natural groundwater (catchment), also known as a descending water collector. Constructed in areas with distinct terrain features where groundwater naturally surfaces. Access to the collected water may not be available (such as when water immediately enters a closed pipeline).
- **Type:** Water catchment facility
- **OSM Tag:** `man_made = spring_box`

### Water Well (Колодец)
- **Description:** A man-made structure for collecting natural groundwater (catchment), also known as an ascending water collector. Built in areas without natural groundwater outlets. Access to the collected water may not be available (when there is no free access to water lifting devices and/or water immediately enters a closed pipeline).
- **Type:** Water catchment facility
- **OSM Tag:** `man_made = water_well`

### Precipitation Collector (Дождевой водосбор)
- **Description:** A man-made structure for collecting rainwater. Typically constructed in arid regions with mountainous terrain and significant areas of monolithic rock surfaces to capture water during brief rainfall for traditional livestock farming. Collected water usually flows into a precipitation reservoir, often a separate structure.
- **Type:** Water catchment facility
- **OSM Tag:** None defined

### Precipitation Reservoir (Дождевой накопитель)
- **Description:** A man-made structure for accumulating rainwater. Water typically comes from a precipitation collector, which is often a separate structure.
- **Type:** Water access facility
- **OSM Tag:** None defined

## Water Access Facilities

### Water Fountain (Труба / Фонтан)
- **Description:** A water access device with continuous flow of incoming water (typically of natural origin).
- **Type:** Water access facility
- **OSM Tag:** `amenity=fountain`, `drinking_water=yes`

### Drinking Fountain (Питьевой фонтанчик)
- **Description:** A water access device with a specific design for drinking, with continuous or intermittent water flow. Typically located in recreational areas or urban territories.
- **Type:** Water access facility
- **OSM Tag:** `man_made = drinking_fountain`

### Decorative Fountain (Декоративный фонтан)
- **Description:** An uninterrupted water source for decorative purposes. Not intended for drinking. May recirculate water.
- **Type:** Water access facility
- **OSM Tag:** None defined

### Water Tap (Кран)
- **Description:** An interruptible water source.
- **Type:** Water access facility
- **OSM Tag:** None defined

### Standpipe (Водоколонка)
- **Description:** An outdoor water access device in countries with cold climates.
- **More info:** https://www.fire-service.ru/kolonki/vodorazbornaya-kolonka
- **Type:** Water access facility
- **OSM Tag:** None defined

### Shower (Душ)
- **Description:** A beach shower.
- **Type:** Water access facility
- **OSM Tag:** None defined

### Watering Place (Водопой)
- **Description:** A device for providing water to farm animals. Often used by people in mountainous areas with hot climates. Consists of an open reservoir accessible to animals, which receives water through a pipe (or tap) from a remote water intake.
- **Type:** Water access facility
- **OSM Tag:** `amenity = watering place`

### Water Point (Водозабор)
- **Description:** A device for filling containers with water in significant quantities. For example: for filling caravan containers, yachts in marinas, etc.
- **Type:** Water access facility
- **OSM Tag:** `amenity=water_point`, `water_way=water_point`

### Break Pressure Tank (Резервуар сброса давления)
- **Description:** A device for reducing pressure in a pipeline when delivering water over long distances in mountainous terrain. Depending on the design, access to the transported water may or may not be available.
- **Type:** Water access facility
- **OSM Tag:** None defined

### Fire Tank (Пожарный резервуар)
- **Description:** A structure or reservoir for storing water supplies in case of fire, typically in arid areas. May or may not provide access to water.
- **Type:** Water access facility
- **OSM Tag:** `emergency = fire_water_pond`, `emergency = water_tank`

### Water Vending Machine (Автомат по продаже питьевой воды)
- **Description:** A machine for selling water in bottled form or in bulk. Typically found in settlements with problematic water supply.
- **Type:** Water access facility
- **OSM Tag:** None defined

## Original Table Format

| EN | RU | Description / Описание | Natural object / Природный объект | Water catchment facility / Водосборное устройство (Каптаж) | Water access facility / Водоразборное устройство (Место доступа) | OSM tag mapping / Соответствие тэгам OSM |
|---|---|---|---|---|---|---|
| Within the Scope | | | | | | |
| Natural graundwater outlet | Выход грунтовых вод | Место постоянного выхода грунтовых вод на поверхность. Может отсутствовать возможность для удобного забора воды. Место, где может впоследствии быть оборудован родник, колодец или другой водосбор. | v | | | natural = spring |
| Easy water access point | Подход к воде | Локация или рукотворный объект, в месте расположения которого по каким-то причинам удобно набирать воду из естественного резервуара или водотока (мостки на заболоченном берегу, возможный спуск к труднодоступному руслу, пересечение тропы с ручьем и т.д.) | v | | v | - |
| Water spring | Родник | Рукотворная конструкция для сбора природных грунтовых вод (каптаж), так называемый низходящий водосбор. Устраивается на местности с выраженным рельефом в местах естественного выхода грунтовых вод на поверхность. Может отсутствовать доступ к собираемой воде (в случае, когда вода сразу поступает в закрытый трубопровод). | | v | | man_made = spring_box |
| Water well | Колодец | Рукотворная конструкция для сбора природных грунтовых вод (каптаж), так называемый восходящий водосбор. Устраивается в отсутствии естественных выходов грунтовых вод на поверхность. Может отсутствовать доступ к собираемой воде (в случае, когда нет свободного доступа к водоподъемным приспособлениям и/или вода сразу поступает в закрытый трубопровод). | | v | | man_made = water_well |
| Precipitation collector | Дождевой водосбор | Рукотворная конструкция для сбора дождевой воды. Устраивается как правило в засушливых районах с горным рельефом и значительными площадями монолитных скальных поверхностей для улавливания воды в процессе непродолжительных осадков для целей традиционного животноводства. Собранная вода обычно поступает в дождевой накопитель, часто являющийся отдельным сооружением. | | v | | - |
| Precipitation reservoir | Дождевой накопитель | Рукотворная конструкция для аккумуляции дождевой воды. Вода как правило поступает из дождевого водосбора, часто являющийся отдельным сооружением. | | | v | - |
| Water fountain | Труба / Фонтан | Водоразборное устройство с постоянным истечением поступающей воды (как правило природного происхождения). | | | v | amenity=fountain (drinking_water=yes) |
| Drinking fountain | Питьевой фонтанчик | Водоразборное устройство специфической конструкции для питья, с постоянным или прерываемым истечением поступающей воды. Расположен как правило в рекреационной зоне или на урбанизированных территориях. | | | v | man_made = drinking_fountain |
| Decorative fountain | Декоративный фонтан | Непрерываемый источник воды декоративного назначения. Употребление для питья не предусмотрено. Возможна рециркуляция воды. | | | v | |
| Water tap | Кран | Источник воды прерываемого действия. | | | v | |
| Standpipe | Водоколонка | Уличное водоразборное устройство в странах с холодным климатом https://www.fire-service.ru/kolonki/vodorazbornaya-kolonka | | | v | |
| Shower | Душ | Пляжный душ | | | v | |
| Watering place | Водопой | Устройство для организации водопоя сельскохозяйственных животных. Как правило используется и людьми в горных районах с жарким климатом. Представляет из себя открытый резервуар, доступный для животных, в который по трубе (или через кран) посупает вода из удаленного водозабора. | | | v | amenity = watering place |
| Water point | Водозабор | Устройство для заправки емкостей водой в значительных количествах. Например: для заправки емкостей автодомов (caravan), яхт в маринах и т.д. | | | v | amenity=water_point water_way=water_point |
| Break pressure tank | Резервуар сброса давления | Устройство для сброса давления в трубопроводе при подаче воды на большие расстояния в горной местности. В зависимости от конструкции может присутствовать или отсутствовать доступ к транспортируемой воде. | | | v | |
| Fire tank | Пожарный резервуар | Конструкция или водоем для хранения запасов воды на случай пожара, как правило в засушливой местности. Возможно с доступом к воде или же без него. | | | v | emergency = fire_water_pond emergency = water_tank |
| Water vending machine | Автомат по продаже питьевой воды | Аппарат для продажи воды в бутилированном виде или в разлив. Как правило в населенных пунктах с проблемным водоснабжением. | | | v | |


