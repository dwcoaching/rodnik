# Local dev: pulling prod data

```sh
./db-pull.sh           # fresh dump from prod + restore locally
./db-restore.sh        # restore from latest local dump (pulls if missing)
./photos-pull.sh       # photos for reports from last 7 days
./photos-pull.sh 30    # custom window (days)
```

Local dump: `dumps/manual.sql.gz`
