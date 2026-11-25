<section class="privacy-guide" aria-labelledby="privacy-guide-title">
    <h2 id="privacy-guide-title">What does an anonymity check actually show?</h2>
    <p>FEZADAN Anonymity Check is a free browser privacy report. It shows the connection address this website sees and information your browser allows a page to read. Use it to compare observations before and after a browser or network change, not to certify that you are anonymous.</p>
    <p lang="tr">Anonimlik testi: Bu araç, görünen bağlantı adresini ve tarayıcının okunabilir bilgilerini gösterir. VPN güvenliği, DNS sızıntısı veya internette tam anonimlik garantisi vermez. Arayüz ve ayrıntılı açıklamalar İngilizcedir.</p>

    <h3>Connection address, location and Tor</h3>
    <p>Your public IP address is the address visible to this service. With a VPN, proxy or Tor connection, it may belong to an exit server rather than your own network. ISP and ASN identify the network associated with an address; an ordinary provider name does not prove that a VPN is absent.</p>
    <p>Country and city are IP-based estimates, not your physical position. Local geolocation databases may be missing or outdated. Tor evidence comes from a local exit-node snapshot or trusted network-edge evidence. Missing or stale evidence remains unknown. A Tor exit match does not verify every application on your device uses Tor.</p>

    <h3>WebRTC addresses and DNS limitations</h3>
    <p>WebRTC can make connection addresses available through ICE candidates. When a first-party TLS TURN service is configured, the report compares observed public candidates with the address of the page connection. A difference is evidence to review, not an automatic verdict about your VPN. Hidden, local or mDNS candidates do not establish a public IP leak.</p>
    <p>If the browser API is unavailable, a request times out or the first-party service is not configured, the check says so. A test that did not finish is not proof of protection. <strong>This tool does not perform a DNS leak test.</strong> Identifying a DNS resolver requires a dedicated measurement that this report does not make.</p>
    <p>IPv4 and IPv6 coverage is reported separately. A completed IPv4 sample does not establish IPv6 coverage, and an absent candidate is not proof that a family is disabled. TURN relay addresses belong to the relay service, not the visitor, and are excluded from additional-address findings.</p>

    <h3>Browser fingerprint signals</h3>
    <p>Canvas, WebGL, audio, fonts, screen dimensions, language, timezone and user-agent information can contribute to browser fingerprinting. This report describes readable signals and sampled outputs where supported. It does not compare you with a population of browsers, measure fingerprint uniqueness or claim to identify you.</p>
    <p>Privacy preferences such as Global Privacy Control and Do Not Track are reported when exposed by your browser. They are preference signals, not proof that every website respects them. Unavailable permissions or blocked APIs are kept separate from completed measurements.</p>

    <h3>How to use the report</h3>
    <ol>
        <li>Open this page in the browser and connection you want to examine, then select “Run report”.</li>
        <li>Start with the interpretation, suggested actions and measurement gaps. Open “Technical evidence” for the underlying observations. Tasks ended is not a count of tests passed.</li>
        <li>If you change a VPN connection or browser setting, run a new report and compare the relevant observations.</li>
        <li>Share the saved address only if you are comfortable sharing its remaining browser and network details.</li>
    </ol>

    <h3>Privacy and saved reports</h3>
    <p>Browser checks contact this origin, not third-party lookup services, map providers, social accounts, extensions or local ports. Network and location lookups use local data. Optional WebRTC testing uses a first-party TURN service. Your connection address necessarily reaches the web server and the hosting/network infrastructure serving the page.</p>
    <p>When a report finishes, the tool attempts to save a shareable copy for 24 hours. Saved copies omit request headers and mask IP addresses; they can still contain location estimates, network names and browser details. Anyone with the link can read the copy while it is available. Saved reports are client-generated, not independently verified, and are marked not to be indexed. Expired links return an unavailable-report page.</p>
    <p>The application writes a daily pseudonymous visit log using salted address and user-agent hashes, together with limited network observations. This is not a promise that hosting providers keep no logs. Read the <a href="https://fezadan.org/en/privacy">FEZADAN privacy policy</a> for site-wide information.</p>

    <h3>Frequently asked questions</h3>
    <details><summary>Can this tell me whether my VPN is safe?</summary><p>No. It can show an observed connection address and supported browser signals. It cannot audit a VPN provider, its logging policy, other applications or unmeasured traffic paths.</p></details>
    <details><summary>Does a report with no findings mean I am anonymous?</summary><p>No. No finding only describes the measurements that completed in this session. Account activity, tracking across sites, DNS and traffic outside this browser remain outside the report.</p></details>
    <details><summary>Why can two browsers produce different results?</summary><p>Browsers expose different APIs and apply different privacy protections. Extensions, permissions, platform settings and network conditions can also change what is measurable. Compare evidence, not an overall score.</p></details>
    <details><summary>Do I need an account, and will my report stay online?</summary><p>No account is required. A saved report is available for 24 hours, not a permanent record. IP masking reduces exposure but does not remove every identifying detail, so treat a shared report as public to anyone who has its link.</p></details>
    <p class="guide-links"><a href="https://fezadan.org/en">About the FEZADAN publication</a><a href="https://github.com/shenfurkan/Fezadan" rel="noopener">Read the source code</a></p>
</section>
