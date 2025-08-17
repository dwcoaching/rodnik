<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rodnik Heatmap - Yandex Analytics</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin="" />
    
    <!-- Leaflet.heat plugin CSS -->
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        
        #map {
            width: 100vw;
            height: 100vh;
        }
        
        .controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            min-width: 250px;
        }
        
        .control-group {
            margin-bottom: 15px;
        }
        
        .control-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .control-group input[type="range"] {
            width: 100%;
        }
        
        .control-value {
            display: inline-block;
            margin-left: 10px;
            color: #666;
            font-size: 14px;
        }
        
        .stats {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 14px;
        }
        
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            z-index: 1000;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div id="loading" class="loading">Loading heatmap data...</div>
    <div id="map"></div>
    
    <div class="controls">
        <h3 style="margin-top: 0;">Heatmap Controls</h3>
        
        <div class="control-group">
            <label for="radius">
                Radius: <span id="radiusValue" class="control-value">25</span>
            </label>
            <input type="range" id="radius" min="10" max="50" value="25" step="1">
        </div>
        
        <div class="control-group">
            <label for="blur">
                Blur: <span id="blurValue" class="control-value">15</span>
            </label>
            <input type="range" id="blur" min="5" max="30" value="15" step="1">
        </div>
        
        <div class="control-group">
            <label for="maxZoom">
                Max Zoom: <span id="maxZoomValue" class="control-value">13</span>
            </label>
            <input type="range" id="maxZoom" min="1" max="18" value="13" step="1">
        </div>
        
        <div class="control-group">
            <label for="minOpacity">
                Min Opacity: <span id="minOpacityValue" class="control-value">0.05</span>
            </label>
            <input type="range" id="minOpacity" min="0" max="1" value="0.05" step="0.01">
        </div>
        
        <div class="control-group">
            <label for="gradient">
                Gradient Intensity: <span id="gradientValue" class="control-value">0.6</span>
            </label>
            <input type="range" id="gradient" min="0.1" max="1" value="0.6" step="0.1">
        </div>
        
        <div class="stats">
            <strong>Statistics:</strong><br>
            Total Points: <span id="totalPoints">0</span><br>
            Total Views: <span id="totalViews">0</span><br>
            Total Visitors: <span id="totalVisitors">0</span>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
            crossorigin=""></script>
    
    <!-- Leaflet.heat plugin -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    
    <script>
        // Initialize the map - view covering Europe from Canarias to Ural and including Turkey
        const map = L.map('map').setView([48, 25], 4); // Centered on Europe with appropriate zoom
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '',
            maxZoom: 19
        }).addTo(map);
        
        // Remove Leaflet attribution
        map.attributionControl.setPrefix('');
        
        let heatLayer = null;
        let heatData = [];
        let rawData = null;
        
        // Gradient configurations
        const gradients = {
            '0.1': {0.1: 'blue', 0.3: 'lime', 0.5: 'yellow', 0.7: 'orange', 1: 'red'},
            '0.2': {0.2: 'blue', 0.4: 'lime', 0.6: 'yellow', 0.8: 'orange', 1: 'red'},
            '0.3': {0.3: 'blue', 0.5: 'lime', 0.65: 'yellow', 0.8: 'orange', 1: 'red'},
            '0.4': {0.4: 'blue', 0.6: 'lime', 0.7: 'yellow', 0.85: 'orange', 1: 'red'},
            '0.5': {0.5: 'blue', 0.65: 'lime', 0.75: 'yellow', 0.9: 'orange', 1: 'red'},
            '0.6': {0.6: 'blue', 0.7: 'lime', 0.8: 'yellow', 0.95: 'orange', 1: 'red'},
            '0.7': {0.7: 'blue', 0.8: 'lime', 0.85: 'yellow', 0.95: 'orange', 1: 'red'},
            '0.8': {0.8: 'blue', 0.85: 'lime', 0.9: 'yellow', 0.95: 'orange', 1: 'red'},
            '0.9': {0.9: 'blue', 0.92: 'lime', 0.94: 'yellow', 0.97: 'orange', 1: 'red'},
            '1': {0.95: 'blue', 0.96: 'lime', 0.97: 'yellow', 0.99: 'orange', 1: 'red'}
        };
        
        // Load data from JSON file
        fetch('/analytics/yandex-results.json')
            .then(response => response.json())
            .then(data => {
                rawData = data;
                
                // Calculate statistics
                let totalViews = 0;
                let totalVisitors = 0;
                let pointCount = 0;
                
                // Process data for heatmap
                for (const key in data) {
                    const point = data[key];
                    const lat = parseFloat(point.latitude);
                    const lng = parseFloat(point.longitude);
                    const intensity = point.views;
                    
                    heatData.push([lat, lng, intensity]);
                    
                    totalViews += parseInt(point.views);
                    totalVisitors += parseInt(point.visitors);
                    pointCount++;
                }
                
                // Update statistics
                document.getElementById('totalPoints').textContent = pointCount.toLocaleString();
                document.getElementById('totalViews').textContent = totalViews.toLocaleString();
                document.getElementById('totalVisitors').textContent = totalVisitors.toLocaleString();
                
                // Create initial heat layer
                createHeatLayer();
                
                // Hide loading message
                document.getElementById('loading').style.display = 'none';
                
                // Set bounds to cover Europe from Canarias to Ural, including Turkey
                // Southwest: Canarias, Northeast: Ural Mountains
                const europeBounds = L.latLngBounds(
                    [27.6, -18.2],  // Southwest: Canarias
                    [61.5, 65]      // Northeast: Ural Mountains
                );
                map.fitBounds(europeBounds);
            })
            .catch(error => {
                console.error('Error loading heatmap data:', error);
                document.getElementById('loading').innerHTML = 'Error loading data: ' + error.message;
            });
        
        function createHeatLayer() {
            // Remove existing layer if it exists
            if (heatLayer) {
                map.removeLayer(heatLayer);
            }
            
            // Get current control values
            const radius = parseInt(document.getElementById('radius').value);
            const blur = parseInt(document.getElementById('blur').value);
            const maxZoom = parseInt(document.getElementById('maxZoom').value);
            const minOpacity = parseFloat(document.getElementById('minOpacity').value);
            const gradientValue = document.getElementById('gradient').value;
            
            // Create new heat layer with current settings
            heatLayer = L.heatLayer(heatData, {
                radius: radius,
                blur: blur,
                maxZoom: maxZoom,
                minOpacity: minOpacity,
                gradient: gradients[gradientValue] || gradients['0.6']
            }).addTo(map);
        }
        
        // Add event listeners for controls
        document.getElementById('radius').addEventListener('input', function(e) {
            document.getElementById('radiusValue').textContent = e.target.value;
            createHeatLayer();
        });
        
        document.getElementById('blur').addEventListener('input', function(e) {
            document.getElementById('blurValue').textContent = e.target.value;
            createHeatLayer();
        });
        
        document.getElementById('maxZoom').addEventListener('input', function(e) {
            document.getElementById('maxZoomValue').textContent = e.target.value;
            createHeatLayer();
        });
        
        document.getElementById('minOpacity').addEventListener('input', function(e) {
            document.getElementById('minOpacityValue').textContent = e.target.value;
            createHeatLayer();
        });
        
        document.getElementById('gradient').addEventListener('input', function(e) {
            document.getElementById('gradientValue').textContent = e.target.value;
            createHeatLayer();
        });
    </script>
</body>
</html>