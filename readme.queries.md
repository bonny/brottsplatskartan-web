
```sql

# n mest lästa händelserna
select 
year(created_at) as createdYear, created_at, crime_event_id,
count(crime_event_id) as crimeEventIdCount
from crime_views
where created_at <= '2020-01-01'
and created_at >= '2019-01-01'
group by crime_event_id
order by crimeEventIdCount DESC
limit 10

```



## De tio mest lästa polishändelserna 2020

Det är inte nödvändigtvis de största händelserna mediamässigt som får flest visningar. 
Lokala händelser engagerar! 

Här är i alla fall de tio händelser som flest besökare läst under 2020.

Polisbil har kolliderat med EPA-traktor under uttryckning i Täby
https://brottsplatskartan.se/stockholms-lan/trafikolycka-taby-168151

Polisen söker efter flera gärningspersoner efter att två personer i tonåren blivit rånade på sina mobiltelefoner
https://brottsplatskartan.se/stockholms-lan/ran-tyreso-174264

Försvunnen pojke, Sibbarp
https://brottsplatskartan.se/skane-lan/forsvunnen-person-malmo-sibbarp-169592

En ung man har blivit påhoppad bakifrån i Hässelby Villastad av en gärningsman och skadad med vasst föremål i ena benet.
https://brottsplatskartan.se/stockholms-lan/misshandel-stockholm-hasselby-villastad-149111

Brand i flerfamiljshus, Bålsta
https://brottsplatskartan.se/uppsala-lan/brand-habo-balsta-151830

Norrbotten, ett urval av nattens polisverksamhet
https://brottsplatskartan.se/norrbottens-lan/sammanfattning-natt-norrbottens-lan-norrbotten-161429

Två bilar i krock på länsväg 1136 utanför Norsholm
https://brottsplatskartan.se/ostergotlands-lan/trafikolycka-norrkoping-norsholm-164531

En person är misstänkt för misshandel i Bohult
https://brottsplatskartan.se/orebro-lan/misshandel-karlskoga-bohult-169518

Brottsplatsbevakning med anledning av misstänkt grovt brott
https://brottsplatskartan.se/uppsala-lan/ovrigt-knivsta-177423

Sammanfattning av nattens polisverksamhet i polisområde Jönköping
https://brottsplatskartan.se/jonkopings-lan/sammanfattning-natt-jonkopings-lan-jonkoping-157111
