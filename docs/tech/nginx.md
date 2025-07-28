# Nginx

Tile files should not be cached, please update the nginx config:

```
    location /tiles/ {
        add_header Cache-Control "no-cache";
        
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location /watered-tiles/ {
        add_header Cache-Control "no-cache";
        
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
```