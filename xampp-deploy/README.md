# Southern Flood Response · XAMPP deployment

The React interface uses the PHP/MySQL API in `xampp-deploy/api/`. SFR creates the seven operational tables (`flood_reports`, `help_requests`, `volunteer_teams`, `flood_points`, `shelters`, `announcements`, and `notifications`) in the `southern_flood` database. Shelter CRUD and the primary report, help request, volunteer application, map point, announcement, and notification flows use MySQL. Demo seed data is explicitly illustrative, not live emergency information.

## Install on Windows XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`, select **Import**, and import `xampp-deploy/database.sql`. The script creates all seven tables and adds example records only where they are missing. Shelter rows use insert-on-duplicate-key without overwriting edited details.
3. Build the React site with `npm run build`.
4. Copy the contents of `xampp-deploy/site/` into `C:\xampp\htdocs\Southern-Flood-Response\`. This ready-to-run folder includes the current production build and matching PHP files in `api/`.
5. Visit `http://localhost/Southern-Flood-Response/`. The shelter menu is at `/Southern-Flood-Response/shelters`; Admin shelter management is under `/admin` → **ศูนย์พักพิง**.
6. Verify the connection at `http://localhost/Southern-Flood-Response/api/health.php`; it returns `ok: true`, the shelter count, and row counts for each table.

## Develop with Vite

Run Apache and MySQL first, then `npm run dev`. Vite proxies `/api/*` to the XAMPP API. The browser should use `http://localhost:5173` so PHP session cookies and the proxy share the same host.

Admin sign-in is verified by PHP session before shelter, flood point, report review, help assignment, volunteer approval, and announcement changes. Set the Apache/PHP environment variable `SFR_ADMIN_PIN` to a long, unique value before signing in; when it is missing, Admin login is disabled. Keep this value out of the frontend and Git. Deploy public sites only with a trusted HTTPS certificate and configure Apache to redirect HTTP to HTTPS. The included headers enable HSTS only on HTTPS, so local XAMPP over HTTP continues to work. The seed addresses and phone numbers are demonstration data; verify with local authorities before relying on them during an emergency.

Weather is served by `api/weather.php?province=<Thai province>` using the Open-Meteo forecast service; it returns current model conditions and a seven-day forecast. River gauges are served by `api/water-stations.php?province=<Thai province|ทุกจังหวัด>` from the National Hydroinformatics Data Center (ThaiWater). Both connectors cache results in the PHP temporary directory (30 minutes for weather, 5 minutes for station readings), label the provider and retrieval time, and mark stale cached responses when the upstream service is unavailable. The Apache/PHP host must have outbound HTTPS access; PHP cURL or `allow_url_fopen` must be enabled.

RID SWOC feeds are available at `api/rid-monitoring.php?type=water|location|hiiwater|rain1h|rain24h` and back the live-feed panel on the Situation page. `water` and `location` use the documented Tele RID endpoints; `hiiwater`, `rain1h`, and `rain24h` use the separately documented HII endpoints. The PHP proxy allowlists these endpoints, applies a five-minute cache, and returns provider data without inventing measurements. If SWOC requires a bearer token, set the Apache/PHP process environment variable `SFR_RID_API_TOKEN` (never a `VITE_` variable and never in React source). Optionally set `SFR_RID_API_BASE` to override the documented service host. On Windows XAMPP, configure the variables in Apache's environment and restart Apache, then open `http://localhost/Southern-Flood-Response/api/rid-monitoring.php?type=water`. A `401/403` means the token or access must be resolved with the provider; other upstream failures are shown as unavailable and successful cached responses are marked stale when used. SWOC responses stay raw until their schema and station-coordinate fields are confirmed; ThaiWater remains the normalized station-and-coordinate feed used by the map/list. Do not treat model or station readings as emergency confirmation.

Open-Meteo data attribution is shown in the UI and is provided under CC BY 4.0. TMD also publishes public seven-day regional/provincial forecasts through its data service; its catalog lists the feed as XML and an update cadence of Monday/Wednesday/Friday, while Open-Meteo supplies the current/hourly fields used by this interface. Official data usage conditions apply. River-level observations follow the ThaiWater station data model; station readings may be delayed or missing, so the interface displays the measurement time and status where available. These readings are not a complete flood assessment for every community.

Statistics are served by `api/statistics.php?period=24h|7d|30d` and calculated from database rows. Other resources are served by `api/records.php?resource=<table>` using GET, POST, PATCH, or DELETE as supported by the resource. Public reports and requests are inserted together with notification records; report/request map points are created when GPS coordinates are included. Private report, request, and volunteer contact details require the Admin PHP session. Public map reads only approved flood points and rounds coordinates. This demo does not yet provide volunteer accounts, team login, or uploaded image storage.

Shelter coordinates are required and restricted to southern Thailand, so a missing coordinate cannot silently fall back to Hat Yai. Admin sign-in limits repeated wrong PIN attempts within the current PHP session. This is a local-demo safeguard; it is not a replacement for named accounts, IP-based rate limiting, and production authentication.

Database settings default to XAMPP's local `root` account with an empty password. Override with `SFR_DB_HOST`, `SFR_DB_NAME`, `SFR_DB_USER`, and `SFR_DB_PASSWORD` if your local XAMPP uses different credentials. Do not expose phpMyAdmin or the default database credentials to a public network.


Shelter coordinates are required and restricted to southern Thailand so a missing location cannot silently point to Hat Yai. Admin sign-in limits repeated wrong PIN attempts within the current PHP session. This is a basic local-demo safeguard, not production account security. Public users cannot modify notification read state. 

