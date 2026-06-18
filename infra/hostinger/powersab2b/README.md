Hostinger shared hosting deploy layout for `powersab2b.com`.

- `public_html/.htaccess`: routes `/backend` into Laravel and everything else into the Passenger Node app.
- `public_html/backend.php`: normalizes the `/backend` prefix before Laravel handles the request.
- `web/server.cjs`: production Next.js entrypoint used by Passenger.
