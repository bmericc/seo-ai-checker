<p class="text-secondary updated-at">Last updated: November 16, 2026</p>

<p>
    This Privacy Policy describes the personal data collected and
    processed by the <strong>{{ config('app.name') }}</strong> web panel
    ("the panel", "the service") and the <strong>SEO Checker</strong>
    mobile app ("SEO / AI Overview Checker" on the App Store and Google
    Play). Anyone can register with a Google account or with an email
    address and password; however, new accounts cannot use the panel's
    features until an admin approves them.
</p>

<h2>1. Who processes the data?</h2>
<ul>
    <li>
        <strong>Panel data</strong> — Your account, domains and check
        results are processed by the operator of the panel you connect
        to, on that panel's server. The panel is open source and can be
        run by different people on their own servers; in that case the
        operator of that panel is the data controller.
    </li>
    <li>
        <strong>Mobile app data</strong> — The SEO Checker mobile app is
        published by Bahri Canlı. The publisher is the controller of the
        usage and crash data described in section 7.
    </li>
</ul>
<p>
    For questions and requests, contact
    @if (config('mail.from.address') && config('mail.from.address') !== 'hello@example.com')
        <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
    @else
        the panel administrator.
    @endif
</p>

<h2>2. What data is collected?</h2>
<ul>
    <li>
        <strong>Account information</strong> — Name and email address.
        When you sign in with Google, this comes from Google. When you
        register with an email and password, your password is stored
        only as a one-way hash; the plain password is never stored.
    </li>
    <li>
        <strong>Account status and preferences</strong> — Approval
        status, admin role, email verification status, language and
        notification preferences.
    </li>
    <li>
        <strong>Sessions and access tokens</strong> — The web panel uses
        session and CSRF cookies to keep you signed in; they are not
        used for marketing or tracking. Access tokens are created for
        the mobile app and for AI assistants (MCP) you authorize. In the
        mobile app the token is kept in the device's secure storage
        (iOS Keychain / Android Keystore).
    </li>
    <li>
        <strong>Records you create</strong> — The domains, keywords,
        competitors and AI assistant prompts you add, and the result of
        every check (search rankings, AI Overview and AI assistant
        visibility, on-page SEO, Lighthouse scores, WHOIS and security
        checks) are stored in the database. These records belong to you
        and are visible only to members you share them with and to
        panel admins.
    </li>
    <li>
        <strong>Connected accounts (optional)</strong> — If you connect
        Google Search Console / Google Analytics or Bing Webmaster Tools,
        their access tokens are stored encrypted and used only to read
        data for your domains. AI provider API keys you enter for a
        domain are also stored encrypted.
    </li>
    <li>
        <strong>Share links</strong> — If you create a public share link
        for a report, anyone with the link can view that report without
        signing in. You can revoke the link at any time.
    </li>
</ul>
<p>
    The panel does not collect personal data from visitors of the
    sites you track; it only analyzes the public page content and
    search results of the domains/URLs you specify.
</p>

<h2>3. How is the data used?</h2>
<ul>
    <li>Authentication, account approval and access control.</li>
    <li>Running the SEO and AI visibility checks you request and showing their history.</li>
    <li>Sending email verification, password reset and the notification emails you turn on.</li>
    <li>Improving the stability and usability of the mobile app (section 7).</li>
</ul>
<p>
    Data is not used for advertising or profiling and is not sold to
    third parties.
</p>

<h2>4. Third-party services</h2>
<p>
    To run checks, the panel sends only the information required
    (domain, URL, keyword or prompt text) to the services below. Which
    services are used depends on the panel's configuration.
</p>
<ul>
    <li>
        <strong>Google OAuth</strong> — For Google sign-in and the
        optional Search Console / Analytics connection. Google's
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">privacy policy</a>
        applies.
    </li>
    <li>
        <strong>Google Search, PageSpeed Insights and Chrome UX Report</strong> —
        For rankings, AI Overview detection and performance metrics.
    </li>
    <li>
        <strong>DataForSEO</strong> — If configured, for search result
        and keyword data.
    </li>
    <li>
        <strong>AI providers (OpenAI, Anthropic, Google Gemini, Perplexity)</strong> —
        Prompt texts are sent to these providers for AI assistant
        visibility checks. We recommend not putting personal data in
        prompts.
    </li>
    <li>
        <strong>Microsoft Bing Webmaster Tools and IndexNow</strong> —
        For Bing data if you connect it; IndexNow submissions send the
        URLs you choose to search engines.
    </li>
    <li>
        <strong>WHOIS and DNS lookups</strong> — Public records are
        queried for domain registration and security information.
    </li>
    <li>
        <strong>Email provider</strong> — To deliver verification,
        password reset and notification emails.
    </li>
    <li>
        <strong>Google Firebase</strong> — Mobile app only; see section 7.
    </li>
</ul>

<h2>5. Data retention</h2>
<p>
    Account and record data is kept until you or an admin deletes it.
    When a domain or keyword is deleted, all of its check history is
    permanently deleted as well. If your approval is revoked, your
    access ends immediately. You can request deletion of your account
    and all of your data as described in section 8.
</p>

<h2>6. Security</h2>
<p>
    Access to the panel is limited to approved accounts over HTTPS.
    Passwords are hashed and third-party access tokens are encrypted.
    On shared domains, read/write access is granted per member.
</p>

<h2>7. Mobile app</h2>
<p>
    The SEO Checker mobile app connects to the panel server you enter
    on the sign-in screen and reads your panel data directly from that
    server; your panel data is not sent to the app's publisher. The app
    uses Google Firebase services to improve stability and usability:
</p>
<ul>
    <li>
        <strong>Firebase Crashlytics</strong> — When the app crashes,
        crash details (stack trace, app version, device model, OS
        version) are sent.
    </li>
    <li>
        <strong>Google Analytics for Firebase</strong> — Usage
        statistics such as which screens are opened, session length,
        app version, device model, OS, language and approximate country
        are collected.
    </li>
</ul>
<p>
    The app <strong>does not collect the advertising identifier (IDFA /
    Android Advertising ID)</strong>, shows no ads, keeps ad
    personalization signals off and does not track you across other
    companies' apps or websites. Your name, email address and panel
    content are not added to Firebase data. This data is processed by
    Google; see
    <a href="https://firebase.google.com/support/privacy" target="_blank" rel="noopener">Privacy and Security in Firebase</a>
    for details.
</p>

<h2>8. Your rights</h2>
<p>
    Under the Turkish Personal Data Protection Law (KVKK, Art. 11) and,
    where applicable, the GDPR, you have the right to access, correct
    and delete your data, to restrict its processing and to object.
    For any request, including deletion of your account and all of your
    data, write to the contact address in section 1 from your
    account's email address. Requests are answered within 30 days.
</p>

<h2>9. Children</h2>
<p>
    The service is intended for professional use and is not directed
    at children under 13.
</p>

<h2>10. Changes</h2>
<p>
    This policy may be updated from time to time; the current version
    and its last-updated date are always published on this page.
</p>
