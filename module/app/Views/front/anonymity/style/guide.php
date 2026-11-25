        .privacy-guide {
            width: min(740px, calc(100% - 40px));
            margin: 24px auto 64px;
            padding-top: 32px;
            border-top: 1px solid var(--border);
            color: var(--fg-muted);
            font-size: 1.075rem;
            line-height: 1.7;
        }
        .privacy-guide h2, .privacy-guide h3 {
            color: var(--fg);
            font-family: var(--font-body);
            font-weight: 700;
            line-height: 1.3;
        }
        .privacy-guide h2 { font-size: 1.8rem; margin-bottom: 20px; }
        .privacy-guide h3 { font-size: 1.25rem; margin: 32px 0 12px; }
        .privacy-guide p { margin-bottom: 16px; }
        .privacy-guide ol { padding-left: 24px; margin: 16px 0; }
        .privacy-guide li { margin-bottom: 8px; }
        .privacy-guide a { color: var(--fg); text-underline-offset: 4px; }
        .privacy-guide details { border-bottom: 1px solid var(--border); padding: 4px 0; }
        .privacy-guide summary { color: var(--fg); cursor: pointer; padding: 12px 0; min-height: 44px; }
        .privacy-guide details p { margin: 0 0 12px; }
        .guide-links { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-top: 24px; }
        .guide-links a { display: inline-flex; align-items: center; min-height: 44px; }
        .privacy-guide :focus-visible { outline: 2px solid var(--fg); outline-offset: 4px; }
        body.start-screen-active .brand-mark { position: static; order: 1; margin: 0 auto 24px; }
