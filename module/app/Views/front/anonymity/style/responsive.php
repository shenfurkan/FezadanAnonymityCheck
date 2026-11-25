        /* Animations */
        @keyframes pulse {
            0% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        @keyframes ecg-heartbeat {
            0%, 100% {
                transform: scaleY(0.04);
                opacity: 0.35;
            }
            15% {
                transform: scaleY(1.3);
                opacity: 1;
            }
            30% {
                transform: scaleY(0.04);
                opacity: 0.35;
            }
            45% {
                transform: scaleY(1.5);
                opacity: 1;
            }
            60% {
                transform: scaleY(0.04);
                opacity: 0.35;
            }
        }

        /* Keep branding out of the start button on short/landscape screens. */
        @media (max-height: 600px) {
            body.start-screen-active .brand-mark {
                position: static;
                order: 1;
                margin: 24px auto;
            }
        }

        /* Media Queries */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .matrix-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .summary-strip {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .geo-card {
                grid-row: auto;
            }
            .finding-card {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .matrix-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .assessment-columns { grid-template-columns: 1fr; gap: 16px; }
            .assessment { padding: 22px 8px; }
            .container {
                width: min(100% - 28px, 1180px);
                padding-top: 74px;
            }

            .brand-mark {
                top: auto;
                left: 14px;
                bottom: 14px;
                right: auto;
                padding: 0;
            }

            .brand-mark img {
                width: 26px;
                height: 26px;
            }

            .brand-name {
                font-size: 1.18rem;
            }

            .brand-kicker {
                font-size: 0.52rem;
            }

            .start-screen {
                margin-top: 42px;
            }

            .start-screen h1 {
                font-size: clamp(2rem, 8vw, 4rem);
                max-width: 12ch;
            }

            .finding-card {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .matrix-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .param-list {
                grid-template-columns: 1fr;
            }
            .score-card {
                grid-template-columns: 1fr;
                text-align: left;
                justify-items: stretch;
            }
            .geo-content {
                flex-direction: column;
                align-items: stretch;
            }
            .geo-details-list {
                grid-template-columns: repeat(2, 1fr);
                text-align: left;
            }
        }

        @media (max-width: 600px) {
            .summary-strip {
                grid-template-columns: 1fr;
            }
            .matrix-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .param-list {
                grid-template-columns: 1fr;
            }
            .score-card {
                grid-template-columns: 1fr;
                text-align: left;
                justify-items: stretch;
            }
        }

        @media (max-width: 480px) {
            .container {
                width: min(100% - 16px, 1180px);
                padding-top: 60px;
                padding-bottom: 70px;
            }

            .start-screen {
                margin-top: 24px;
            }

            .start-screen h1 {
                font-size: clamp(1.4rem, 9vw, 2.2rem);
                gap: 12px;
                max-width: 12ch;
            }

            .start-screen p.subtitle {
                font-size: 1.15rem;
                line-height: 1.6;
                margin-bottom: 24px;
            }

            .start-screen p.subtitle span {
                padding: 3px 8px;
            }

            .btn-scan {
                width: 100%;
                padding: 14px 28px;
                font-size: 1.5rem;
                box-shadow: none;
            }

            .matrix-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .geo-details-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .geo-details-item {
                padding: 10px 12px;
            }

            #map-wrapper {
                height: 220px;
            }

            .card {
                padding: 16px;
            }

            .summary-strip {
                gap: 12px;
            }

            .summary-item {
                padding: 14px;
            }
            
            .brand-mark {
                bottom: 8px;
                right: 8px;
            }
        }
