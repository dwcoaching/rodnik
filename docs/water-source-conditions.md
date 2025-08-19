# Water Source Conditions

- [Location](#location)
- [Water Availability](#water-availability)
- [Water Quality](#water-quality)
- [Accessibility](#accessibility)
  - [Object Access](#object-access)
  - [Water Access](#water-access)
- [Condition](#condition)
- [Special Conditions](#special-conditions)
- [Original Table](#original-table)

> **Note:** Original document [here](https://docs.google.com/spreadsheets/d/1sDnIOWgyEtAAMGeFpSk0KXX2Qfu8A3PpZNFN_MxcWN0/edit?gid=645214391#gid=645214391).

This document categorizes different conditions that can be associated with water sources.

<a name="location"></a>
## [Location](#location)

- **Found (найден)** - The water source exists at the specified location.
- **Imprecise Coordinates (координаты не точные)** - The exact location of the water source may differ from the coordinates provided.
- **Object Not Found (объект не обнаружен)** - The water source could not be located at the specified coordinates.

<a name="water-availability"></a>
## [Water Availability](#water-availability)

- **Water Present (вода есть)** - Water is available at the source.
- **Low Water (воды мало)** - The source has limited water.
- **No Water / Dry (воды нет (сухо))** - The source is dry with no water available.

<a name="water-quality"></a>
## [Water Quality](#water-quality)

- **Good Water (вода хорошая)** - The water quality is good.
- **Poor Water (вода плохая)** - The water quality is poor.

<a name="accessibility"></a>
## [Accessibility](#accessibility)

<a name="object-access"></a>
### [Object Access (доступ к объекту)](#object-access)

- **Publicly Accessible (общедоступен)** - The water source is freely accessible to the public.
- **Limited Access (доступ ограничен)** - Access to the water source is limited. For example:
  - The source is located in a park with restricted hours
  - The access is partially restricted due to other factors

- **Inaccessible (недоступен)** - The water source cannot be accessed. For example:
  - Fire water tank located behind a fence with barbed wire
  - Source for internal use located in a closed, guarded area

<a name="water-access"></a>
### [Water Access (доступ к воде)](#water-access)

- **Available (есть)** - Water can be accessed directly.
- **Difficult (затруднен)** - Water access is possible but difficult. For example:
  - Well without bucket and rope
  - Water supply tap requires tools or is located in a non-obvious place

- **Unavailable (отсутствует)** - Water cannot be accessed. For example:
  - Well is locked
  - Well is part of a private water supply system where public access is not intended
  - Pressure release device in the pipeline is locked

<a name="condition"></a>
## [Condition](#condition)

- **Needs Repair (требуется ремонт)** - The source or water supply is out of order due to minor damage.
- **Ruins (руины)** - The source or water supply is destroyed.

<a name="special-conditions"></a>
## [Special Conditions](#special-conditions)

- **Aggressive Vegetation (агрессивная растительность)** - The source is overgrown with nettles or thorny plants due to low visitation.
- **Decorative Water Source (декоративный источник воды)** - For example:
  - Friendship of Peoples Fountain at VDNKh
  - Decorative pond/cascade in a park
  
- **Trash (мусор)** - Trash creates sanitary or emotional barriers to water use.

<a name="original-table"></a>
## [Original Table](#original-table)

| Группа | Параметр | Значения | Отображение в UI | Толкование |
|--------|---------|---------|----------------|------------|
| локация | | найден | - | |
| | | координаты не точные | | |
| | | объект не обнаружен | | |
| обводненность | | вода есть | | |
| | | воды мало | | |
| | | воды нет (сухо) | | |
| качество воды | | вода хорошая | | |
| | | вода плохая | | |
| доступность | доступ к объекту | общедоступен | - | |
| | | доступ ограничен | | Например: <br>= источник находится на территории парка, доступ в который не является круглосуточным; |
| | | недоступен | | Например: <br>= пожарная емкость расположена за забором с колючей проволокой;<br>= источник для внутреннего использования, находящийся на закрытой охраняемой территории; |
| | доступ к воде | есть | - | |
| | | затруднен | | Например:<br>= колодец без ведра и веревки;<br>= кран подачи воды на раздачу требует наличия инструмента или расположен в неочевидном месте; |
| | | отсутствует | | Например: <br>= колодец заперт на замок;<br>= колодец является водозабором частного водопровода и доступ посторонних лиц к воде не предусмотрен;<br>= устройство сброса давления в трубопроводе заперто на замок; |
| разрушения | | требуется ремонт | | источник или водоподача выведены из строя из-за незначительных повреждений |
| | | руины | | источник или водоподача разрушены |
| специальные | агрессивная растительность | | | Источник зарос крапивой или колючими растениями вследствии низкой посещаемости |
| | декоративный источник воды | | | Например: <br>= Фонтан дружбы народов на ВДНХ;<br>= декоративный пруд/каскад в парке; |
| | мусор | | | Мусор создает санитарные или эмоциональные препятствия для использования воды |


