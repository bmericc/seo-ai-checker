<p class="text-secondary updated-at">Last updated: November 16, 2026</p>

<p>
    These Terms of Service govern the use of the
    <strong>{{ config('app.name') }}</strong> web panel and the
    <strong>SEO Checker</strong> mobile app ("the service"). By
    registering, signing in or using the mobile app, you agree to these
    terms and to the
    <a href="{{ route('privacy-policy') }}">Privacy Policy</a>.
</p>

<h2>1. Description of the service</h2>
<p>The service offers the following checks and tools:</p>
<ul>
    <li>Ranking tracking in Google search results (SERP).</li>
    <li>Visibility detection in the Google AI Overview box and in AI assistants (ChatGPT, Claude, Gemini, Perplexity, etc.).</li>
    <li>On-page SEO, technical SEO, security, WHOIS and sitemap analysis.</li>
    <li>Google PageSpeed Insights (Lighthouse) and Chrome UX Report performance metrics.</li>
    <li>Optional Google Search Console, Google Analytics and Bing Webmaster Tools integrations.</li>
    <li>Competitor analysis, report sharing, llms.txt drafts and IndexNow submission.</li>
</ul>
<p>
    The mobile app is a client for viewing the data of the panel you
    connect to; it requires a reachable panel server and an approved
    account.
</p>

<h2>2. Accounts and access</h2>
<ul>
    <li>
        You can register with a Google account or with an email address
        and password. New accounts cannot use the panel's features until
        an admin approves them.
    </li>
    <li>
        You are responsible for keeping your account information
        accurate and your password and access tokens (mobile app, MCP
        tokens) confidential.
    </li>
    <li>
        An admin may revoke an account's approval or delete it when
        these terms are violated or the security of the service requires
        it; access then ends immediately.
    </li>
    <li>
        You can delete your account yourself at any time from the web
        panel or the mobile app (see the Privacy Policy, section 8).
    </li>
</ul>

<h2>3. Acceptable use</h2>
<p>When using the service, you agree to:</p>
<ul>
    <li>
        Only scan domains/pages that you own or are permitted to
        analyze.
    </li>
    <li>
        Not run heavy or bulk queries that violate the terms of service
        of search engines or AI providers, robots.txt rules or
        applicable law.
    </li>
    <li>
        Not use the service to harm, abuse or attempt to bypass the
        security of other systems.
    </li>
    <li>
        Take responsibility for the read/write access you grant to
        members you share domains with, and for the public report links
        you create.
    </li>
    <li>
        Settle the use and fees of third-party API keys you enter
        directly with the respective provider.
    </li>
</ul>

<h2>4. Nature of results and disclaimer</h2>
<p>
    Search results and AI answers vary by account, location, device and
    time, and search engines may occasionally block automated requests.
    Therefore:
</p>
<ul>
    <li>
        Ranking, AI Overview and AI assistant visibility detection is
        <strong>best-effort</strong>; a result shown in the service may
        differ from a live search, and some checks may return no result.
    </li>
    <li>
        As search engines change their page structure and APIs, some
        analyses may temporarily stop working.
    </li>
</ul>
<p>
    The service is provided "as is", without any warranty of accuracy,
    availability or fitness for a particular purpose. Neither the
    operator of the service nor the publisher of the mobile app is
    liable for the consequences of business or marketing decisions made
    based on its results.
</p>

<h2>5. Dependency on third-party services</h2>
<p>
    The service depends on third-party services such as Google,
    Microsoft, DataForSEO, AI providers and Firebase. Outages, changes
    or quota limits of these services may affect its functionality.
</p>

<h2>6. Intellectual property</h2>
<p>
    The panel's source code is published under an open-source license.
    The content you add and your check results belong to you.
</p>

<h2>7. Changes to or termination of the service</h2>
<p>
    The operator may change, suspend or permanently discontinue the
    panel, and the publisher may do the same for the mobile app,
    without prior notice.
</p>

<h2>8. Governing law</h2>
<p>
    These terms are governed by the laws of the Republic of Türkiye.
    Your mandatory rights under consumer protection law are not
    affected.
</p>

<h2>9. Changes</h2>
<p>
    These terms may be updated from time to time; the current version
    and its last-updated date are always published on this page.
</p>

<h2>10. Contact</h2>
<p>
    For questions about these terms, contact
    @if (config('mail.from.address') && config('mail.from.address') !== 'hello@example.com')
        <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
    @else
        the panel administrator.
    @endif
</p>
