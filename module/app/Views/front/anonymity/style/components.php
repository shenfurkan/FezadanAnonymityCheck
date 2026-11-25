        /* Dashboard Layout Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Card System */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-header {
            margin-bottom: 20px;
        }

        .card-title {
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--fg-muted);
        }

        /* Report completion, not a privacy score */
        .score-card {
            display: grid;
            grid-template-columns: 1fr;
            align-items: center;
            min-height: 200px;
        }

        .report-count {
            color: var(--fg-muted);
            font-family: var(--font-mono);
            font-size: 0.8rem;
            margin-bottom: 12px;
        }

        .score-number {
            color: var(--fg);
            font-weight: 600;
        }

        .report-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 12px 24px;
            margin-bottom: 20px;
        }

        .report-actions a {
            color: var(--fg);
            text-underline-offset: 4px;
        }

        a:focus-visible, button:focus-visible {
            outline: 2px solid var(--fg);
            outline-offset: 4px;
        }

        .score-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .score-verdict {
            font-family: var(--font-display);
            font-weight: 400;
            font-size: 2.2rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 8px;
            color: var(--fg);
            transition: color 0.5s ease;
        }

        .score-desc {
            color: var(--fg-muted);
            font-size: 1rem;
            line-height: 1.5;
        }

        #report-coverage { margin-top: 12px; }

        .assessment {
            padding: 28px 0;
            border-top: 1px solid var(--border-strong);
            border-bottom: 1px solid var(--border-strong);
            margin-bottom: 32px;
        }

        .assessment h2 {
            font-size: clamp(1.7rem, 3vw, 2.3rem);
            font-weight: 400;
            line-height: 1.2;
            margin: 12px 0 16px;
            max-width: 32ch;
        }

        #assessment-summary {
            max-width: 85ch;
            color: var(--fg-muted);
            font-size: 1.15rem;
            line-height: 1.6;
        }

        .assessment-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin: 28px 0;
        }

        .assessment-columns > div { min-width: 0; }
        .assessment h3 { font-size: 1.2rem; margin-bottom: 14px; }
        .assessment li { margin: 0 0 12px 22px; padding-left: 4px; line-height: 1.55; }
        #assessment-gaps { color: var(--fg-muted); }
        .report-methodology { border-top: 1px solid var(--border); padding-top: 16px; }
        summary { cursor: pointer; text-underline-offset: 4px; }
        summary:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; }
        .report-methodology summary { color: var(--accent); }
        .technical-evidence summary, .report-methodology summary { min-height: 44px; padding: 8px 0; }
        .report-methodology dl { margin-top: 20px; max-width: 85ch; }
        .report-methodology dt { font-weight: 700; margin-top: 16px; }
        .report-methodology dd, .report-methodology p { color: var(--fg-muted); }
        .report-methodology p { margin-top: 16px; max-width: 85ch; }
        .report-methodology a { color: var(--accent); text-underline-offset: 4px; }
        .section-intro { color: var(--fg-muted); margin-top: 10px; max-width: 85ch; }

        .summary-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-item {
            min-width: 0;
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 8px;
            padding: 18px;
        }

        .summary-label {
            font-family: var(--font-mono);
            color: var(--fg-dim);
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }

        .summary-value {
            overflow-wrap: anywhere;
            color: var(--fg);
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 400;
            line-height: 1.1;
        }

        /* Cached Report Alert */
        #cached-alert {
            display: none;
            background-color: var(--surface-accent);
            border: 1px solid var(--border-strong);
            padding: 10px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-family: var(--font-mono);
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--fg);
            align-items: center;
            gap: 8px;
        }

        /* Observed Parameters Card */
        .parameters-card {
            margin-top: 24px;
        }

        .param-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px 24px;
        }

        .param-item {
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }

        .param-label {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--fg-dim);
            margin-bottom: 4px;
        }

        .param-value {
            font-family: var(--font-mono);
            font-size: 1rem;
            font-weight: 600;
            word-break: break-all;
            color: var(--fg);
        }

        .param-desc {
            font-size: 1rem;
            color: var(--fg-muted);
            margin-top: 3px;
        }

        /* Geographic Details Card */
        .geo-card {
            grid-row: span 2;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .geo-content {
            width: 100%;
            margin-bottom: 20px;
        }

        .geo-details-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            width: 100%;
        }

        .geo-details-item {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .param-value-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .geo-flag-inline {
            height: 16px;
            border-radius: 2px;
            object-fit: contain;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }

        .origin-flag {
            height: 38px;
            border-radius: 4px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            vertical-align: middle;
        }

        .geo-flag-card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Custom Leaflet Dark Popup styling */
        .leaflet-popup-content-wrapper, .leaflet-popup-tip {
            background: var(--surface) !important;
            color: var(--fg) !important;
            border: 1px solid var(--border) !important;
            box-shadow: none !important;
            border-radius: 6px !important;
        }
        .leaflet-popup-content {
            margin: 8px 12px !important;
            font-family: var(--font-mono) !important;
            font-size: 0.8rem !important;
        }

        /* Local location panel */
        #map-wrapper {
            position: relative;
            width: 100%;
            height: 310px;
            border-radius: 6px;
            border: 1px solid var(--border);
            overflow: hidden;
            background: var(--bg);
        }

        #map {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        #osm-map {
            width: 100%;
            height: 100%;
            background: var(--bg);
        }

        #osm-map .leaflet-tile {
            filter: invert(1) hue-rotate(180deg) brightness(0.6) contrast(1.2);
        }

        .location-panel {
            width: min(360px, 100%);
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 8px;
            padding: 20px;
            text-align: left;
        }

        .location-point {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--info);
            margin-bottom: 18px;
        }

        .location-panel-title {
            font-family: var(--font-display);
            font-weight: 400;
            font-size: 1.65rem;
            margin-bottom: 6px;
        }

        .location-panel-copy {
            color: var(--fg-muted);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Matrix Section */
        .matrix-section {
            margin-bottom: 40px;
        }

        .matrix-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .matrix-cell {
            min-width: 0;
            gap: 8px;
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .matrix-name {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: var(--fg);
        }

        /* Badge Styles */
        .badge {
            flex-shrink: 0;
            font-family: var(--font-mono);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: 1px solid transparent;
            display: inline-block;
        }

        .badge.testing {
            background-color: var(--surface-accent);
            border-color: var(--border);
            color: var(--fg-dim);
        }

        .badge.neutral {
            background-color: transparent;
            border-color: var(--border);
            color: var(--fg-muted);
        }

        .badge.exposed {
            background-color: var(--exposed-bg);
            border-color: var(--exposed);
            color: var(--exposed);
        }

        .badge.warning {
            background-color: var(--warning-bg);
            border-color: var(--warning);
            color: var(--warning);
        }

        .badge.blocked {
            background-color: var(--safe-bg);
            border-color: var(--safe);
            color: var(--safe);
        }

        .badge.unsupported {
            background-color: transparent;
            color: var(--fg-dim);
            font-style: italic;
        }

        /* Detailed Findings Section */
        .findings-section {
            margin-top: 40px;
        }

        .findings-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 16px;
        }

        .findings-category-header {
            font-family: var(--font-display);
            font-size: 1.8rem;
            color: var(--fg);
            margin-top: 24px;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 250, 243, 0.1);
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .finding-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 24px;
            display: grid;
            grid-template-columns: 180px minmax(0, 1.5fr) minmax(0, 1fr);
            gap: 32px;
            align-items: start;
            box-shadow: none;
            position: relative;
            overflow: hidden;
        }

        .finding-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--info);
        }

        .finding-card.severity-high::before, .finding-card.severity-critical::before {
            background: var(--exposed);
        }

        .finding-card.severity-medium::before {
            background: var(--warning);
        }

        .finding-card.severity-low::before {
            background: var(--border);
        }

        .finding-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .finding-name {
            font-family: var(--font-display);
            font-weight: 400;
            font-size: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            line-height: 1.1;
        }

        .finding-evidence {
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--fg);
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 250, 243, 0.05);
            padding: 12px 16px;
            border-radius: 6px;
            overflow-wrap: anywhere;
            margin-top: 6px;
            margin-bottom: 16px;
        }

        .technical-evidence { margin-top: 16px; }
        .technical-evidence summary { font-size: 0.95rem; color: var(--accent); }

        .finding-impact-block {
            display: flex;
            flex-direction: column;
        }

        .finding-title-small {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--fg-dim);
            margin-bottom: 4px;
        }

        .finding-text {
            font-size: 1.1rem;
            color: var(--fg);
            line-height: 1.5;
        }

        .finding-mitigation-block {
            display: flex;
            flex-direction: column;
        }

        .finding-mitigation-text {
            font-size: 1.05rem;
            color: var(--fg-muted);
            line-height: 1.5;
        }
