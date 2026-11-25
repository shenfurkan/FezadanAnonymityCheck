<?php // Local-only location estimate: no tile provider receives the visitor address. ?>
    function initMap(lat, lon) {
        const container = document.getElementById('map');
        if (!container) return;

        const latNum = typeof lat === 'number' ? lat : Number(lat);
        const lonNum = typeof lon === 'number' ? lon : Number(lon);
        const hasCoordinates = lat !== null && lon !== null && lat !== '' && lon !== ''
            && Number.isFinite(latNum) && Number.isFinite(lonNum) && Math.abs(latNum) <= 90 && Math.abs(lonNum) <= 180;
        const coordinateText = hasCoordinates
            ? latNum.toFixed(4) + ', ' + lonNum.toFixed(4)
            : 'Coordinates unavailable';
        const locality = [serverSignals.city, serverSignals.region, serverSignals.countryName]
            .filter(Boolean)
            .join(', ') || 'Location unavailable';

        container.innerHTML = '';

        container.innerHTML = `
            <div class="location-panel">
                <div class="location-panel-title">${hasCoordinates ? 'IP location estimate' : 'Location unavailable'}</div>
                <div class="location-panel-copy">${escapeHtml(locality)}<br>${escapeHtml(coordinateText)}</div>
                <p class="param-desc">Resolved locally or from trusted edge headers. No map tiles or location permissions are requested.</p>
            </div>
        `;
    }
