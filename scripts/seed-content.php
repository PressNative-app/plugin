<?php
/**
 * Seed WordPress with PressNative demo content: categories, pages, and blog posts.
 *
 * Run via WP-CLI from the WordPress root:
 *   wp eval-file wp-content/plugins/pressnative-app/scripts/seed-content.php
 *
 * This populates the site as a PressNative company blog / demo site
 * with real content about mobile apps, case studies, and the platform.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	echo "Run via: wp eval-file " . __FILE__ . "\n";
	exit( 1 );
}

/* ─── Site Identity ─────────────────────────────────────────────────── */
$site_title   = 'PressNative';
$site_tagline = 'Turn Your WordPress Site Into a Native Mobile App';

/* ─── Categories ────────────────────────────────────────────────────── */
$categories = array(
	array( 'name' => 'Featured',            'slug' => 'featured',            'description' => 'Editor\'s picks for the hero carousel' ),
	array( 'name' => 'Mobile Strategy',     'slug' => 'mobile-strategy',     'description' => 'Insights on mobile-first growth and engagement' ),
	array( 'name' => 'Case Studies',        'slug' => 'case-studies',        'description' => 'Real-world success stories from PressNative publishers' ),
	array( 'name' => 'Product Updates',     'slug' => 'product-updates',     'description' => 'New features, improvements, and platform news' ),
	array( 'name' => 'Developer Resources', 'slug' => 'developer-resources', 'description' => 'Technical guides and best practices' ),
	array( 'name' => 'Industry Insights',   'slug' => 'industry-insights',   'description' => 'Trends shaping mobile publishing and content delivery' ),
);

/* ─── Pages ─────────────────────────────────────────────────────────── */
$pages = array(
	array(
		'title'   => 'About PressNative',
		'slug'    => 'about',
		'content' => '<!-- wp:heading {"level":2} -->
<h2>Our Mission</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>PressNative bridges the gap between WordPress and native mobile apps. We believe every publisher, blogger, and small business deserves the engagement and retention benefits of a native mobile presence — without hiring a development team or rebuilding from scratch.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>The Problem We Solve</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Mobile web traffic now accounts for over 60% of all internet usage, yet mobile browsers deliver a fundamentally compromised experience. Slow load times, no push notifications, buried bookmarks, and zero home screen presence mean your best content gets lost in the noise.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Progressive Web Apps promised a solution but fell short — limited iOS support, no App Store presence, and a second-class experience that users can feel. Meanwhile, building a custom native app costs $50,000–$200,000 and months of development time.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Our Approach</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>PressNative takes your existing WordPress content and serves it through truly native Android and iOS shells built with Jetpack Compose and SwiftUI. Your content stays in WordPress where you already manage it. Our plugin exposes a structured layout API that the native apps consume in real time.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The result: a fast, beautiful, native app that updates the moment you hit "Publish" in WordPress — no app store resubmission required.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>The Team</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>PressNative was founded by developers who spent years building custom mobile apps for publishers and realized the process was broken. We set out to make native mobile apps as easy to launch as installing a WordPress plugin.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Based in the United States, our small team is obsessed with performance, design, and giving publishers the tools they need to own their audience relationship.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'title'   => 'Features',
		'slug'    => 'features',
		'content' => '<!-- wp:heading {"level":2} -->
<h2>Everything You Need for a Native Mobile App</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>PressNative is a complete platform for turning your WordPress site into a native mobile app. Here\'s what you get out of the box.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Native Performance</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Built with Jetpack Compose (Android) and SwiftUI (iOS), your app renders at 60fps with smooth animations, native gestures, and platform-standard navigation. No WebView wrappers. No hybrid compromises.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Push Notifications</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Send targeted push notifications directly from your WordPress dashboard. Notify all subscribers when you publish, or send custom messages to iOS, Android, or recently active users. Track delivery and engagement in the analytics dashboard.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Real-Time Content Sync</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Publish a post in WordPress and it appears in the app instantly. No rebuild, no app store update, no waiting. Your content management workflow stays exactly the same.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Customizable Branding</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Define your own colors, typography, and logo. Your app looks like your brand, not a generic template. WCAG AA contrast validation ensures accessibility.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Flexible Home Screen Layout</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Arrange your home screen with drag-and-drop components: hero carousel for featured content, post grids with configurable columns, category navigation, page lists, and ad placements. Preview changes in real time before publishing.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Analytics Dashboard</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Understand your audience with built-in analytics. Track views by content type, see device breakdowns, monitor top-performing posts, and measure push notification engagement — all from your WordPress admin panel.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Search</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Full-text search powered by your WordPress database. Users find content instantly with a native search experience that feels right at home on their device.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Ad Monetization</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Integrate AdMob banner ads directly into your app layout. Place them between components on the home screen to monetize your mobile traffic without disrupting the reading experience.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'title'   => 'How It Works',
		'slug'    => 'how-it-works',
		'content' => '<!-- wp:heading {"level":2} -->
<h2>Three Steps to Your Native App</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Getting your WordPress site into the App Store and Google Play is simpler than you think.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Step 1: Install the Plugin</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Upload the PressNative plugin to your WordPress site and activate it. The plugin creates a structured REST API that serves your content, branding, and layout configuration to the native apps. No server changes required — it works with any WordPress host.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Step 2: Customize Your App</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Open the PressNative settings in your WordPress admin. Define custom colors and typography. Upload your logo, configure your home screen layout, and select which categories to feature. Use the live preview to see exactly how your app will look on iOS and Android.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Step 3: Go Live</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Connect your site to the PressNative Registry, and we handle the rest. Your app is built, signed, and submitted to the Apple App Store and Google Play Store. Once approved, your readers can download it and start getting push notifications for every new post.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>What Happens Behind the Scenes</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The PressNative plugin exposes a layout API that the native apps consume. When a user opens your app, it fetches the home screen configuration, branding, and content from your WordPress site in real time. Posts are rendered natively — not in a WebView — for maximum performance.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>When you publish new content, the app reflects it immediately. When you change your brand colors or rearrange the home screen, the app updates on the next launch. You stay in WordPress; your readers get a premium native experience.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'title'   => 'Privacy Policy',
		'slug'    => 'privacy-policy',
		'content' => '<!-- wp:heading {"level":2} -->
<h2>Privacy Policy</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Effective Date:</strong> February 1, 2026</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>PressNative ("we," "our," or "us") respects your privacy. This policy explains how we collect, use, and protect information when you use our website (pressnative.app), WordPress plugin, and native mobile applications.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Information We Collect</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Device Information:</strong> When you install a PressNative-powered app, we collect your device type (iOS or Android) and a Firebase Cloud Messaging token for push notifications. We do not collect your name, email, or personal identifiers through the app.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Usage Analytics:</strong> We collect anonymous usage data including page views, post views, category views, and search queries. This data is aggregated and used to provide analytics to site publishers. It cannot be tied to individual users.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Website Visitors:</strong> Our website uses standard server logs. If you create an account or subscribe to a plan, we collect your email address and payment information (processed securely by Stripe).</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>How We Use Information</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We use collected information to: deliver push notifications you\'ve opted into, provide aggregated analytics to publishers, process payments, improve our services, and communicate about your account or subscription.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Data Sharing</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We do not sell personal information. We share data only with: Stripe (payment processing), Firebase (push notification delivery), and Google Cloud (infrastructure hosting). Each provider is bound by their own privacy policies and data processing agreements.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Data Retention</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Device tokens are retained as long as the app is installed. Analytics data is retained for 90 days. Account information is retained for the duration of your subscription plus 30 days after cancellation.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Contact</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>For privacy questions, email us at privacy@pressnative.app.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'title'   => 'Terms of Service',
		'slug'    => 'terms-of-service',
		'content' => '<!-- wp:heading {"level":2} -->
<h2>Terms of Service</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Effective Date:</strong> February 1, 2026</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>These terms govern your use of the PressNative platform, including our website, WordPress plugin, Registry service, and native mobile applications.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Service Description</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>PressNative provides tools to convert WordPress websites into native mobile applications. The service includes a WordPress plugin, a centralized Registry service, and pre-built native app shells for Android and iOS.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Your Responsibilities</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You are responsible for the content published through your WordPress site and, by extension, through any PressNative-powered app. You must have the right to use and distribute all content, images, and media served through the platform. You agree not to use PressNative to distribute illegal, harmful, or infringing content.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Subscriptions and Billing</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>PressNative offers subscription plans billed monthly or annually through Stripe. You may cancel at any time. Cancellation takes effect at the end of the current billing period. Refunds are handled on a case-by-case basis.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Intellectual Property</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You retain all rights to your content. PressNative retains rights to our platform code, designs, and documentation. The WordPress plugin is distributed under the GPL v2 license consistent with WordPress ecosystem standards.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Limitation of Liability</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>PressNative is provided "as is." We are not liable for damages arising from app store rejections, service interruptions, or content delivery failures beyond our reasonable control. Our total liability is limited to the amount you\'ve paid us in the 12 months preceding any claim.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Changes to Terms</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We may update these terms with 30 days notice via email or in-dashboard notification. Continued use after the notice period constitutes acceptance.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Contact</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Questions about these terms? Email legal@pressnative.app.</p>
<!-- /wp:paragraph -->',
	),
);

/* ─── Blog Posts ─────────────────────────────────────────────────────── */
$posts = array(

	/* ── Mobile Strategy ──────────────────────────────────────────── */
	array(
		'title'   => 'Why Your WordPress Site Needs a Native Mobile App in 2026',
		'excerpt' => 'Mobile web traffic dominates, but browsers can\'t deliver the engagement your content deserves. Here\'s why native apps are the next step for serious publishers.',
		'content' => '<p>If you run a WordPress site with a loyal audience, you\'ve probably noticed a shift. More than 60% of your traffic comes from mobile devices, yet your mobile bounce rate is higher than desktop, session durations are shorter, and return visit rates are lower.</p>

<p>This isn\'t a content problem. It\'s a delivery problem.</p>

<h2>The Mobile Browser Tax</h2>

<p>Mobile browsers impose invisible costs on every visit. Your carefully crafted post competes with thirty open tabs, a cluttered bookmark list, and zero way to bring readers back once they leave. There\'s no push notification. No home screen icon. No offline reading. No native scroll performance.</p>

<p>Even with a fast theme and optimized images, you\'re fighting the browser itself.</p>

<h2>What Native Apps Change</h2>

<p>A native app puts your brand on the home screen — the most valuable real estate on any device. It delivers push notifications that cut through the noise with 4–7x the click-through rate of email. It loads content instantly with native rendering instead of parsing HTML and CSS on every visit.</p>

<p>For publishers, the metrics speak for themselves:</p>

<ul>
<li><strong>3x longer session durations</strong> compared to mobile web</li>
<li><strong>2x higher return visit rates</strong> within 7 days</li>
<li><strong>4–7x push notification engagement</strong> versus email open rates</li>
<li><strong>40% lower bounce rates</strong> on article pages</li>
</ul>

<h2>The Cost Barrier Is Gone</h2>

<p>Historically, native apps cost $50,000–$200,000 and months of development. That priced out every publisher except the largest. PressNative changes the equation: install a WordPress plugin, configure your branding, and your native app is ready for the App Store and Google Play.</p>

<p>Your content stays in WordPress. The app updates in real time. No rebuilds, no resubmissions, no developer dependency.</p>

<h2>The Bottom Line</h2>

<p>If your WordPress site has an audience worth retaining — whether that\'s 1,000 readers or 1,000,000 — a native app is no longer a luxury. It\'s the difference between renting attention in a browser and owning the relationship on a home screen.</p>',
		'cats'    => array( 'featured', 'mobile-strategy' ),
		'image'   => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=800',
	),
	array(
		'title'   => 'Push Notifications: The Engagement Channel You\'re Ignoring',
		'excerpt' => 'Email open rates are declining. Social reach is algorithmic. Push notifications offer a direct, real-time channel to your audience — and most publishers aren\'t using it.',
		'content' => '<p>Every publisher has an email list. Most have social accounts. But how many have a push notification channel? If your answer is "not us," you\'re leaving your most powerful engagement tool on the table.</p>

<h2>The Numbers Tell the Story</h2>

<p>Average email open rates hover around 20% and continue to decline year over year. Social media organic reach has cratered — Facebook pages now reach 2–5% of their followers per post. Meanwhile, push notifications see 40–60% open rates on mobile, with most users engaging within minutes of delivery.</p>

<p>The difference is context. Email arrives in a crowded inbox hours after you send it. Social posts compete with an algorithmic feed. A push notification arrives on a lock screen at the moment you choose, with a direct tap to your content.</p>

<h2>What Makes Push Effective for Publishers</h2>

<p><strong>Immediacy:</strong> Breaking news, new post alerts, and time-sensitive content reach readers in seconds, not hours. For news publishers, this is transformative.</p>

<p><strong>Opt-in quality:</strong> Push subscribers are your most engaged users — they chose to install your app and accept notifications. This audience self-selects for loyalty.</p>

<p><strong>No intermediary:</strong> Unlike social media or email, there\'s no algorithm between you and your reader. Every subscriber sees every notification you send.</p>

<h2>Push Done Right</h2>

<p>Effective push notifications follow three principles:</p>

<ol>
<li><strong>Be relevant.</strong> Not every post warrants a push. Reserve notifications for your best content, breaking stories, and genuine value.</li>
<li><strong>Be timely.</strong> Send during your audience\'s active hours. A notification at 2 AM builds resentment, not engagement.</li>
<li><strong>Be respectful.</strong> One to three notifications per day maximum. More than that and uninstall rates spike.</li>
</ol>

<h2>How PressNative Makes It Easy</h2>

<p>PressNative\'s push notification system lives in your WordPress dashboard. Send to all subscribers or target by platform (iOS/Android) and engagement recency. Track delivery and opens in the analytics dashboard. Optionally, auto-notify on every new publish.</p>

<p>Your readers installed your app because they value your content. Push notifications are how you honor that relationship with timely, relevant delivery.</p>',
		'cats'    => array( 'featured', 'mobile-strategy' ),
		'image'   => 'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=800',
	),
	array(
		'title'   => 'Mobile-First vs. Mobile-Friendly: Why the Distinction Matters',
		'excerpt' => 'A responsive theme makes your site mobile-friendly. But mobile-first means rethinking the entire experience for how people actually use their phones.',
		'content' => '<p>When WordPress theme developers say "mobile-friendly," they mean the layout rearranges itself on small screens. Columns stack. Fonts scale. Menus collapse into hamburger icons. The content is technically accessible on a phone.</p>

<p>But accessible isn\'t the same as optimal.</p>

<h2>The Responsive Compromise</h2>

<p>Responsive design was a massive step forward when Ethan Marcotte coined the term in 2010. It solved the "can you read it on a phone" problem. But it didn\'t solve the "does it feel good on a phone" problem.</p>

<p>A responsive WordPress site on mobile still:</p>
<ul>
<li>Loads the full desktop payload (JavaScript, CSS, fonts, images) then hides what doesn\'t fit</li>
<li>Relies on browser rendering with no access to native gesture systems</li>
<li>Offers no persistent presence on the home screen</li>
<li>Cannot send push notifications (web push has limited iOS support and low adoption)</li>
<li>Fights for attention against every other browser tab</li>
</ul>

<h2>What Mobile-First Actually Means</h2>

<p>Mobile-first means the phone experience is the primary design target, not an afterthought. It means native scroll physics, haptic feedback, platform-standard navigation patterns, and performance that matches the apps users spend 90% of their phone time in.</p>

<p>Users don\'t compare your mobile website to other websites. They compare it to Instagram, their banking app, and their news reader. That\'s the bar.</p>

<h2>Bridging the Gap</h2>

<p>You don\'t need to abandon WordPress to go mobile-first. PressNative lets you keep your existing content management workflow while delivering a genuinely native experience on iOS and Android. The app renders your content with platform-native components — not a WebView wrapper pretending to be native.</p>

<p>Your readers get an app that feels like it belongs on their phone. You get to keep publishing in WordPress. That\'s what mobile-first looks like for publishers in 2026.</p>',
		'cats'    => array( 'mobile-strategy' ),
		'image'   => 'https://images.unsplash.com/photo-1555774698-0b77e0d5fac6?w=800',
	),
	array(
		'title'   => '5 Metrics That Prove Your Readers Want an App',
		'excerpt' => 'Not sure if your audience is ready for a native app? These five analytics signals will tell you.',
		'content' => '<p>The decision to launch a native app shouldn\'t be based on a hunch. It should be based on data you already have. Here are five metrics from your existing WordPress analytics that signal your audience is ready for an app.</p>

<h2>1. Mobile Traffic Exceeds 50%</h2>

<p>Check your Google Analytics device breakdown. If more than half your sessions come from mobile devices, your audience is already choosing phones as their primary way to consume your content. They\'re doing it in a browser because you haven\'t given them an alternative.</p>

<h2>2. High Return Visit Rate</h2>

<p>If your returning visitor percentage is above 30%, you have loyal readers who come back repeatedly. These are your app\'s first adopters. They\'re already committed — an app just makes it easier for them to stay connected.</p>

<h2>3. Email List Engagement Is Declining</h2>

<p>Watch your email open rates over time. If they\'re trending downward despite list growth, your audience isn\'t disengaged — they\'re shifting channels. Push notifications offer a higher-signal alternative to email for content alerts.</p>

<h2>4. Social Referral Traffic Is Unpredictable</h2>

<p>If your traffic from Facebook, Twitter/X, or Instagram swings wildly month to month, you\'re at the mercy of algorithm changes. An app with push notifications gives you a direct channel you control, independent of any social platform\'s business decisions.</p>

<h2>5. Mobile Bounce Rate Is Higher Than Desktop</h2>

<p>A meaningful gap between mobile and desktop bounce rates (more than 10 percentage points) suggests the mobile experience isn\'t meeting user expectations. A native app typically reduces mobile bounce rates by 30–40% because the reading experience is dramatically better.</p>

<h2>What to Do With This Data</h2>

<p>If three or more of these signals are present, your audience is telling you something. They want your content on their terms — fast, native, and always accessible from their home screen. The question isn\'t whether to launch an app. It\'s how soon.</p>',
		'cats'    => array( 'mobile-strategy', 'industry-insights' ),
		'image'   => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800',
	),

	/* ── Case Studies ─────────────────────────────────────────────── */
	array(
		'title'   => 'How a Local News Site Grew Engagement by 340% with PressNative',
		'excerpt' => 'The Eastside Chronicle went from struggling with mobile bounce rates to becoming the most-opened app in their market. Here\'s how they did it.',
		'content' => '<p>The Eastside Chronicle is a hyperlocal news site covering a mid-size metro area. With three full-time reporters and a WordPress site running since 2018, they had a loyal readership but a mobile problem: 65% of their traffic came from phones, but mobile sessions lasted half as long as desktop.</p>

<h2>The Challenge</h2>

<p>Editor-in-chief Marcus Dean saw the trend clearly. "Our readers were on their phones, but the experience wasn\'t keeping them. We\'d publish a breaking story, share it on Facebook, and get a spike — but those readers wouldn\'t come back until the next Facebook share."</p>

<p>Email newsletters helped, but open rates were declining and the turnaround time meant breaking news was already hours old by delivery.</p>

<h2>The Solution</h2>

<p>The Chronicle installed PressNative in March 2025 and launched their app on both stores within weeks. They configured a hero carousel featuring their "Featured" category and a post grid sorted by recency, giving the app a newspaper-front-page feel.</p>

<p>The critical feature: push notifications for breaking news. "When something happens, we publish in WordPress and our readers know about it in seconds," Dean said.</p>

<h2>The Results (After 6 Months)</h2>

<ul>
<li><strong>Session duration:</strong> 4.2 minutes (app) vs. 1.8 minutes (mobile web) — a 133% increase</li>
<li><strong>Return visits within 7 days:</strong> 72% of app users vs. 28% of mobile web users</li>
<li><strong>Push notification open rate:</strong> 48%, compared to 19% email open rate</li>
<li><strong>Overall mobile engagement:</strong> 340% increase in total time spent</li>
<li><strong>App installs:</strong> 8,200 in the first six months, with 61% monthly active</li>
</ul>

<h2>Key Takeaway</h2>

<p>"The app didn\'t change what we publish," Dean said. "It changed how our readers experience it. Same stories, same WordPress workflow, completely different relationship with our audience."</p>',
		'cats'    => array( 'featured', 'case-studies' ),
		'image'   => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800',
	),
	array(
		'title'   => 'From Blog to App: A Food Blogger\'s Journey to 50,000 Downloads',
		'excerpt' => 'Recipe blogger Sarah Kimura never expected her WordPress site to become one of the top food apps in her region. Here\'s what happened.',
		'content' => '<p>Sarah Kimura started her food blog "Salt & Season" on WordPress in 2020. By 2025, she had 200,000 monthly visitors, a thriving email list, and a problem: her readers wanted an easier way to access recipes while cooking.</p>

<h2>The Spark</h2>

<p>"People would email me asking for a recipe app," Sarah said. "I looked into hiring a developer and got quotes between $40,000 and $80,000. For a food blog. That wasn\'t realistic."</p>

<p>She explored Progressive Web Apps but found the experience lacking — especially on iOS, where her majority audience lived. "It never felt like a real app. My readers could tell."</p>

<h2>Going Native with PressNative</h2>

<p>Sarah installed PressNative and had a working app within a day. She organized her recipe categories (Weeknight Dinners, Baking, Meal Prep, Seasonal) and configured them in the app\'s category list. Her hero carousel featured seasonal recipes with high-quality food photography.</p>

<p>"The branding tools let me match the app to my blog perfectly. Same colors, same logo, same feel — just native."</p>

<h2>What Drove Downloads</h2>

<p>Sarah promoted the app through her existing channels: a pinned blog post, email announcement, Instagram stories with App Store links, and QR codes generated by the PressNative shortcode on her most popular recipe posts.</p>

<p>The real growth engine was push notifications. "When I publish a new Weeknight Dinner recipe on Tuesday, my readers get a notification. Open rates are around 52%. Email was around 22% and falling."</p>

<h2>By the Numbers</h2>

<ul>
<li><strong>50,000+ downloads</strong> in 10 months</li>
<li><strong>68% monthly active users</strong></li>
<li><strong>Average session:</strong> 6.1 minutes (vs. 2.3 on mobile web)</li>
<li><strong>Ad revenue increase:</strong> 85% from in-app AdMob placements</li>
</ul>

<h2>Sarah\'s Advice</h2>

<p>"Don\'t wait until you have a million readers. If people come back to your site regularly, they\'ll download an app. The bar is lower than you think — they just need a reason. Push notifications are that reason."</p>',
		'cats'    => array( 'featured', 'case-studies' ),
		'image'   => 'https://images.unsplash.com/photo-1466637574441-749b8f19452f?w=800',
	),
	array(
		'title'   => 'How a Church Community Stayed Connected Through Their App',
		'excerpt' => 'When a 500-member congregation needed a better way to share weekly updates, sermons, and event signups, they turned to PressNative.',
		'content' => '<p>Grace Community Church had been running a WordPress site for years, but engagement was low. Weekly bulletins went out by email (32% open rate), and the website was primarily visited on Sunday mornings to check service times.</p>

<h2>The Need</h2>

<p>Pastor Elaine Torres wanted a way to keep the congregation connected throughout the week. "Our community doesn\'t end on Sunday. We have small groups, volunteer signups, youth events, and pastoral updates. Email wasn\'t reaching everyone, and our website felt like a destination people only visited when they needed something specific."</p>

<h2>The App Approach</h2>

<p>Grace Community launched a PressNative app organized around their WordPress categories: Sermons, Events, Community News, and Volunteer Opportunities. The hero carousel featured upcoming events and the latest sermon.</p>

<p>Push notifications became the primary communication channel. "We send one notification per weekday — Monday is the weekly update, Wednesday is the mid-week devotional, Friday is the weekend event reminder," Torres explained.</p>

<h2>Engagement Shift</h2>

<ul>
<li><strong>App installs:</strong> 380 out of ~500 regular members (76% adoption)</li>
<li><strong>Push open rate:</strong> 63%</li>
<li><strong>Event signup rate:</strong> Increased 140% after launching in-app event posts</li>
<li><strong>Sermon listens:</strong> 45% increase (members access sermon posts with embedded audio)</li>
</ul>

<h2>Beyond the Numbers</h2>

<p>"The app changed how our community communicates," Torres said. "Members who rarely checked email are now engaged daily. Young families who never opened the newsletter are tapping push notifications. It feels more like a group text than a broadcast — even though it\'s one-to-many."</p>

<p>For organizations that run on community — churches, clubs, local nonprofits — a native app isn\'t about technology. It\'s about connection.</p>',
		'cats'    => array( 'case-studies' ),
		'image'   => 'https://images.unsplash.com/photo-1438232992991-995b7058bbb3?w=800',
	),

	/* ── Product Updates ──────────────────────────────────────────── */
	array(
		'title'   => 'Introducing PressNative: WordPress to Native App in Minutes',
		'excerpt' => 'We built PressNative because every WordPress publisher deserves a native mobile app — without the cost and complexity of custom development.',
		'content' => '<p>Today we\'re launching PressNative, a platform that turns any WordPress site into a native mobile app for Android and iOS.</p>

<h2>Why We Built This</h2>

<p>We spent years building custom native apps for publishers. The process was always the same: months of development, tens of thousands of dollars, and a product that immediately fell out of sync with the WordPress site it was supposed to mirror.</p>

<p>Every publisher we worked with asked the same question: "Why can\'t the app just pull from WordPress?"</p>

<p>Now it can.</p>

<h2>How It Works</h2>

<p>PressNative consists of three parts:</p>

<ol>
<li><strong>A WordPress plugin</strong> that exposes your content, branding, and layout through a structured REST API</li>
<li><strong>Native app shells</strong> built with Jetpack Compose (Android) and SwiftUI (iOS) that consume the API and render everything natively</li>
<li><strong>A Registry service</strong> that handles push notifications, analytics, and app management</li>
</ol>

<p>You install the plugin, configure your branding and layout in the WordPress admin, and your app is ready. Content syncs in real time. No code changes. No app store resubmissions.</p>

<h2>What\'s Included</h2>

<ul>
<li><strong>Native performance:</strong> 60fps rendering with Jetpack Compose and SwiftUI — no WebView wrappers</li>
<li><strong>Push notifications:</strong> Send from your WordPress dashboard, target by platform and engagement</li>
<li><strong>Customizable branding:</strong> Fully custom colors, typography, and logo</li>
<li><strong>Flexible layouts:</strong> Hero carousel, post grid, category navigation, page lists, ad placements</li>
<li><strong>Built-in analytics:</strong> Views, top content, device breakdown, push engagement</li>
<li><strong>Live preview:</strong> See your app in iPhone and Android device frames before going live</li>
</ul>

<h2>Who It\'s For</h2>

<p>PressNative is for any WordPress site with an audience worth retaining: news publishers, bloggers, community organizations, churches, local businesses, niche content creators. If you have readers who come back, they deserve a better experience than a mobile browser can offer.</p>

<p>We\'re excited to put this in your hands. Install the plugin and see your content in a new light.</p>',
		'cats'    => array( 'featured', 'product-updates' ),
		'image'   => 'https://images.unsplash.com/photo-1551650975-87deedd944c3?w=800',
	),
	array(
		'title'   => 'New: Real-Time Analytics Dashboard for Your App',
		'excerpt' => 'Track views, engagement, top content, and device breakdown — all from your WordPress admin. No third-party analytics tools required.',
		'content' => '<p>Understanding how your audience uses your app shouldn\'t require a separate analytics platform. Today we\'re launching the PressNative Analytics Dashboard, built directly into your WordPress admin panel.</p>

<h2>What You Can Track</h2>

<p>The dashboard provides a comprehensive view of your app\'s performance:</p>

<p><strong>Key Performance Indicators</strong></p>
<ul>
<li>Total app downloads (favorites)</li>
<li>Total views across all content types</li>
<li>Push notification delivery and engagement</li>
<li>Active subscriber count by platform</li>
</ul>

<p><strong>Content Performance</strong></p>
<ul>
<li>Top posts by view count</li>
<li>Top pages by view count</li>
<li>Top categories by view count</li>
<li>Most-searched queries</li>
</ul>

<p><strong>Audience Insights</strong></p>
<ul>
<li>Views over time (daily or weekly grouping)</li>
<li>Content type breakdown (posts, pages, categories)</li>
<li>Device breakdown (iOS vs. Android)</li>
</ul>

<h2>Date Range Flexibility</h2>

<p>View data for the last 7, 30, or 90 days. All charts and tables update dynamically when you change the range.</p>

<h2>Privacy by Design</h2>

<p>PressNative analytics are fully anonymous. We track content views and device types — never user identities. There are no cookies, no user accounts required, and no personal data collected. Your readers\' privacy is protected by default.</p>

<h2>Getting Started</h2>

<p>The analytics dashboard is available now under PressNative → Analytics in your WordPress admin. Data begins populating as soon as users interact with your app. No configuration required.</p>',
		'cats'    => array( 'product-updates' ),
		'image'   => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800',
	),
	array(
		'title'   => 'Branding Customization: Make Your App Uniquely Yours',
		'excerpt' => 'Full custom color support, typography options, and WCAG AA accessibility validation — all configurable from WordPress.',
		'content' => '<p>Your app should look like your brand, not a generic template. Today we\'re detailing the branding customization system in PressNative.</p>

<h2>Custom Colors and Typography</h2>

<p>Configure your app\'s look with these options:</p>

<ul>
<li><strong>Primary color:</strong> Used for headers, buttons, and navigation</li>
<li><strong>Accent color:</strong> Used for links, highlights, and interactive elements</li>
<li><strong>Background color:</strong> The canvas your content sits on</li>
<li><strong>Text color:</strong> Body text and secondary content</li>
<li><strong>Font family:</strong> Sans-serif, serif, or monospace</li>
<li><strong>Base font size:</strong> 12px to 24px</li>
<li><strong>Logo:</strong> Upload from your WordPress media library</li>
</ul>

<h2>Accessibility Built In</h2>

<p>As you adjust colors, PressNative validates your choices against WCAG AA contrast ratios in real time. If your text color doesn\'t have sufficient contrast against your background, you\'ll see a warning before you save. Accessibility isn\'t an afterthought — it\'s enforced by the tool.</p>

<h2>Live Preview</h2>

<p>Every change you make is reflected in a live device frame preview showing how the app looks on both iPhone and Android. Adjust, preview, and save — your app updates on the next user launch.</p>',
		'cats'    => array( 'product-updates' ),
		'image'   => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=800',
	),

	/* ── Developer Resources ──────────────────────────────────────── */
	array(
		'title'   => 'Getting Started with PressNative: A Complete Setup Guide',
		'excerpt' => 'From plugin installation to app store submission — a step-by-step walkthrough for setting up your first PressNative app.',
		'content' => '<p>This guide walks you through every step of setting up PressNative, from installing the plugin to customizing your app and going live.</p>

<h2>Prerequisites</h2>

<ul>
<li>A self-hosted WordPress site (WordPress.com Business plan or higher for plugin support)</li>
<li>Admin access to your WordPress dashboard</li>
<li>Published content: at least 5–10 posts with featured images</li>
</ul>

<h2>Step 1: Install the Plugin</h2>

<p>Download the PressNative plugin ZIP file from your account dashboard. In WordPress, go to Plugins → Add New → Upload Plugin, and upload the ZIP. Activate the plugin.</p>

<p>You\'ll see a new "PressNative" menu item in your admin sidebar.</p>

<h2>Step 2: Connect to the Registry</h2>

<p>Navigate to PressNative → Dashboard. Enter your Registry URL and API key (provided when you create your PressNative account). Click "Verify Site" to confirm ownership.</p>

<p>The verification process sends a nonce to the Registry, which calls back to your site to confirm you have admin access. This happens automatically — just click the button and wait for the green checkmark.</p>

<h2>Step 3: Configure Branding</h2>

<p>Go to PressNative → App Settings. Here you\'ll set:</p>

<ol>
<li><strong>App name:</strong> What appears under the app icon on users\' home screens</li>
<li><strong>Colors and typography:</strong> Match your website\'s brand identity</li>
<li><strong>Logo:</strong> Upload a square logo (recommended: 512×512px)</li>
</ol>

<p>Use the live preview panel to see how your choices look on iOS and Android.</p>

<h2>Step 4: Configure Layout</h2>

<p>Go to PressNative → Layout Settings. Arrange your home screen components:</p>

<ol>
<li><strong>Hero Carousel:</strong> Select a featured category and set the maximum number of items</li>
<li><strong>Post Grid:</strong> Choose column count and posts per page</li>
<li><strong>Category List:</strong> Select which categories to show</li>
<li><strong>Page List:</strong> Automatically shows your published pages</li>
<li><strong>Ad Placement:</strong> Optional — add your AdMob banner unit ID</li>
</ol>

<p>Drag components to reorder them. The live preview updates as you make changes.</p>

<h2>Step 5: Prepare Your Content</h2>

<p>For the best app experience:</p>

<ul>
<li>Add featured images to all posts (the hero carousel and post grid use these)</li>
<li>Write clear excerpts (shown in post grid cards)</li>
<li>Organize posts into categories (these drive the category list and hero carousel)</li>
<li>Create a "Featured" category for your hero carousel\'s best content</li>
</ul>

<h2>Step 6: Go Live</h2>

<p>Once you\'re happy with the preview, your app is built and submitted to the App Store and Google Play through the PressNative Registry. Approval typically takes 1–3 days for Google Play and 1–7 days for the App Store.</p>

<p>After approval, share your app links everywhere: blog posts, email signatures, social media bios, and QR codes using the <code>[pressnative_qr]</code> shortcode.</p>',
		'cats'    => array( 'developer-resources' ),
		'image'   => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=800',
	),
	array(
		'title'   => 'Optimizing Your WordPress Content for Mobile Apps',
		'excerpt' => 'Great content deserves great presentation. Here\'s how to structure your WordPress posts and pages for the best native app experience.',
		'content' => '<p>PressNative automatically renders your WordPress content in native app components. But a few simple content practices will make your app experience dramatically better.</p>

<h2>Featured Images Are Essential</h2>

<p>Every post should have a featured image. In the app, featured images appear in:</p>
<ul>
<li>The hero carousel (full-width, high-impact)</li>
<li>Post grid cards (thumbnail format)</li>
<li>Post detail headers</li>
<li>Push notification rich media</li>
</ul>

<p>If a post doesn\'t have a featured image, PressNative falls back to the first image in the content, then to your site icon. Set a featured image for consistent, intentional presentation.</p>

<p><strong>Recommended size:</strong> At least 1200×630px for crisp display on high-density screens.</p>

<h2>Write Meaningful Excerpts</h2>

<p>Post excerpts appear in the post grid cards. WordPress auto-generates excerpts by truncating content, which often cuts mid-sentence. Write manual excerpts for cleaner card presentation: 1–2 sentences that summarize the post\'s value proposition.</p>

<h2>Use Categories Strategically</h2>

<p>Categories drive two app features: the category list component and the hero carousel (which pulls from a configurable category). Organize your categories around topics your readers care about — not internal taxonomy.</p>

<p>Good categories: "Recipes," "News," "Tutorials," "Reviews"<br>
Less useful: "Uncategorized," "Q1 2026," "Draft Ideas"</p>

<h2>Structure Content with Headings</h2>

<p>Use H2 and H3 headings to break up long posts. In the app\'s WebView renderer, headings create visual hierarchy and make articles scannable — critical on a small screen where users decide in seconds whether to keep reading.</p>

<h2>Optimize Images in Content</h2>

<p>Large unoptimized images slow down rendering on mobile networks. Use WordPress\'s built-in image compression, or a plugin like ShortPixel or Imagify. Aim for images under 200KB each within post content.</p>

<h2>Test with the Preview</h2>

<p>After configuring your layout, use the PressNative live preview in WordPress admin to see exactly how your content appears. Check the hero carousel, post grid, and individual article views on both device frames.</p>',
		'cats'    => array( 'developer-resources' ),
		'image'   => 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=800',
	),

	/* ── Industry Insights ────────────────────────────────────────── */
	array(
		'title'   => 'The State of Mobile App Usage in 2026',
		'excerpt' => 'App usage continues to grow while mobile browser time declines. Here\'s what the latest data means for publishers.',
		'content' => '<p>Every year the story gets clearer: users spend more time in apps and less time in browsers. The 2026 data confirms the trend is accelerating, not plateauing.</p>

<h2>Key Statistics</h2>

<ul>
<li><strong>App time vs. browser time:</strong> Users spend 88% of their mobile time in native apps, up from 85% in 2023</li>
<li><strong>Average apps used daily:</strong> 9–10 apps, but users spend 90% of app time in their top 5</li>
<li><strong>App downloads:</strong> Global downloads exceeded 250 billion in 2025</li>
<li><strong>Mobile commerce:</strong> 73% of e-commerce transactions now happen on mobile, with apps converting 3x higher than mobile web</li>
</ul>

<h2>What This Means for Publishers</h2>

<p>The numbers have a stark implication: if your content only exists in a browser, you\'re competing for 12% of your readers\' mobile time. The other 88% is spent in native apps.</p>

<p>This doesn\'t mean browsers are dying. It means browsers have become a discovery channel — users find content via search or social, consume it once, and leave. Apps are where habitual, return-visit behavior lives.</p>

<h2>The Publisher App Gap</h2>

<p>Large publishers — The New York Times, ESPN, BBC — have long had native apps with millions of active users. But the vast majority of mid-size and independent publishers still rely exclusively on mobile web. The gap isn\'t because their audiences don\'t want apps. It\'s because building a native app was historically too expensive.</p>

<p>That cost barrier is disappearing. Platforms like PressNative make it possible for any WordPress publisher to have a native presence on the same app stores as the industry giants.</p>

<h2>The Engagement Divide</h2>

<p>Research consistently shows that app users are more engaged than mobile web users by every metric: session duration, pages per session, return visit rate, and conversion rate. For ad-supported publishers, this translates directly to revenue. For community-driven sites, it translates to loyalty.</p>

<p>The question for publishers in 2026 isn\'t "should we have an app?" It\'s "how long can we afford not to?"</p>',
		'cats'    => array( 'featured', 'industry-insights' ),
		'image'   => 'https://images.unsplash.com/photo-1526628953301-3e589a6a8b74?w=800',
	),
	array(
		'title'   => 'Why Progressive Web Apps Aren\'t Enough for Publishers',
		'excerpt' => 'PWAs promised the best of both worlds. For publishers, they delivered neither. Here\'s why native apps remain the gold standard.',
		'content' => '<p>Progressive Web Apps were supposed to be the answer. A web page that acts like an app: installable, works offline, sends push notifications. The pitch was compelling. The reality for publishers has been disappointing.</p>

<h2>The PWA Promise</h2>

<p>When Google championed PWAs in 2015, the vision was clear: one codebase, no app store gatekeeping, automatic updates, and near-native performance. For developers, it was an appealing story. Build once, deploy everywhere.</p>

<h2>Where PWAs Fall Short for Publishers</h2>

<p><strong>iOS support remains limited.</strong> Apple has been a reluctant participant in the PWA ecosystem. Safari\'s service worker implementation is constrained, push notifications were only added to iOS PWAs in 2023 with significant limitations, and there\'s no Add to Home Screen prompt — users must discover the option in a buried share sheet menu.</p>

<p><strong>No App Store presence.</strong> Your PWA doesn\'t appear in the App Store or Google Play. This matters because the app stores are discovery channels. When a reader searches for your publication, finding a native app builds credibility. A PWA has no equivalent discovery path.</p>

<p><strong>Engagement gap.</strong> Studies consistently show PWAs have lower engagement metrics than native apps. Users treat them as "website shortcuts" rather than first-class apps. Push notification opt-in rates for PWAs are roughly 50% lower than for native apps.</p>

<p><strong>Performance ceiling.</strong> PWAs run in a browser engine. No matter how optimized your JavaScript is, you\'re working within the constraints of a web renderer. Native apps built with Jetpack Compose and SwiftUI have direct access to the GPU, native scroll physics, and platform gesture systems. Users can feel the difference.</p>

<h2>When PWAs Make Sense</h2>

<p>PWAs are excellent for utility web apps: calculators, dashboards, tools. For content-heavy publisher sites where engagement, retention, and push notifications drive the business model, native apps deliver measurably better results.</p>

<h2>The Pragmatic Path</h2>

<p>You don\'t need to choose one or the other in theory — but you should invest where the returns are highest. For publishers, that\'s native. PressNative lets you get there without sacrificing your WordPress workflow or hiring a mobile development team.</p>',
		'cats'    => array( 'industry-insights' ),
		'image'   => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=800',
	),
	array(
		'title'   => 'The Hidden Cost of Not Having a Mobile App',
		'excerpt' => 'You can measure the cost of building an app. But are you measuring the cost of not having one? Here\'s the engagement and revenue you\'re leaving behind.',
		'content' => '<p>When evaluating whether to launch a mobile app, publishers naturally focus on the cost of building one. But there\'s a more important question: what is the cost of <em>not</em> having one?</p>

<h2>The Revenue You\'re Not Earning</h2>

<p>App users generate more ad revenue per session than mobile web users. The combination of longer sessions, lower bounce rates, and higher ad viewability means publishers with apps see 2–4x the RPM (revenue per mille) on mobile app inventory compared to mobile web.</p>

<p>For a site with 100,000 monthly mobile sessions, the difference between mobile web RPMs of $4 and app RPMs of $12 is $800/month — or $9,600/year in unrealized revenue.</p>

<h2>The Readers You\'re Losing</h2>

<p>Without push notifications, you rely on readers remembering to visit your site, checking email, or seeing your social posts. Each of these channels has declining reach. Meanwhile, every day without an app is a day your most loyal readers are one algorithm change away from never seeing your content again.</p>

<h2>The Competitors Who Moved First</h2>

<p>In every niche, the first publisher to launch an app captures the "default" position on readers\' home screens. Once a reader has a news app, a recipe app, or a sports app they trust, the switching cost is real. Early movers in your niche are building an audience moat while you wait.</p>

<h2>The Brand Equity Gap</h2>

<p>Having an app in the App Store and Google Play signals legitimacy and investment in your audience. It\'s the difference between a publication and a website. Readers, advertisers, and sponsors all perceive app-first publishers as more established and more serious.</p>

<h2>Doing the Math</h2>

<p>The cost of PressNative is a fraction of custom development. The cost of not having an app is measured in lost engagement, lost revenue, and lost audience — every month you wait.</p>

<p>The best time to launch was last year. The second-best time is now.</p>',
		'cats'    => array( 'mobile-strategy', 'industry-insights' ),
		'image'   => 'https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?w=800',
	),
);


/* ═══════════════════════════════════════════════════════════════════ */
/*  EXECUTION                                                        */
/* ═══════════════════════════════════════════════════════════════════ */

function seed_ensure_loaded() {
	if ( ! function_exists( 'wp_insert_post' ) ) {
		$search = dirname( __FILE__ );
		for ( $i = 0; $i < 6; $i++ ) {
			$search = dirname( $search );
			$wp_load = $search . '/wp-load.php';
			if ( file_exists( $wp_load ) ) {
				require_once $wp_load;
				return;
			}
		}
		echo "Could not find wp-load.php. Run from WordPress root:\n";
		echo "  wp eval-file wp-content/plugins/pressnative-app/scripts/seed-content.php\n";
		exit( 1 );
	}
}

seed_ensure_loaded();

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

echo "\n═══ PressNative Demo Content Seeder ═══\n\n";

/* ── Update site identity ─────────────────────────────────────────── */
echo "── Site Identity ──\n";
update_option( 'blogname', $site_title );
update_option( 'blogdescription', $site_tagline );
echo "  Site title: {$site_title}\n";
echo "  Tagline: {$site_tagline}\n\n";

/* ── Remove default content ───────────────────────────────────────── */
echo "── Cleaning Defaults ──\n";
$hello_world = get_page_by_title( 'Hello world!', OBJECT, 'post' );
if ( $hello_world ) {
	wp_delete_post( $hello_world->ID, true );
	echo "  Deleted: Hello world! post\n";
}
$sample_page = get_page_by_title( 'Sample Page', OBJECT, 'page' );
if ( $sample_page ) {
	wp_delete_post( $sample_page->ID, true );
	echo "  Deleted: Sample Page\n";
}
echo "\n";

/* ── Create categories ────────────────────────────────────────────── */
echo "── Categories ──\n";
$cat_ids = array();
foreach ( $categories as $c ) {
	$term = get_term_by( 'slug', $c['slug'], 'category' );
	if ( $term ) {
		$cat_ids[ $c['slug'] ] = (int) $term->term_id;
		echo "  Exists: \"{$c['name']}\" (id={$cat_ids[$c['slug']]})\n";
	} else {
		$r = wp_insert_term( $c['name'], 'category', array(
			'slug'        => $c['slug'],
			'description' => $c['description'],
		) );
		if ( ! is_wp_error( $r ) ) {
			$cat_ids[ $c['slug'] ] = (int) $r['term_id'];
			echo "  Created: \"{$c['name']}\" (id={$cat_ids[$c['slug']]})\n";
		} else {
			echo "  FAILED: \"{$c['slug']}\" — {$r->get_error_message()}\n";
		}
	}
}
echo "\n";

/* ── Create pages ─────────────────────────────────────────────────── */
echo "── Pages ──\n";
foreach ( $pages as $p ) {
	$existing = get_page_by_path( $p['slug'] );
	if ( $existing ) {
		echo "  Exists: \"{$p['title']}\" (id={$existing->ID})\n";
		continue;
	}

	$page_id = wp_insert_post( array(
		'post_title'   => $p['title'],
		'post_name'    => $p['slug'],
		'post_content' => $p['content'],
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => 1,
	) );

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		echo "  Created: \"{$p['title']}\" (id={$page_id})\n";
	} else {
		echo "  FAILED: \"{$p['title']}\"\n";
	}
}
echo "\n";

/* ── Create posts with featured images ────────────────────────────── */
echo "── Blog Posts ──\n";
$post_count = count( $posts );
$current    = 0;

foreach ( $posts as $p ) {
	$current++;
	echo "  [{$current}/{$post_count}] ";

	$existing = get_page_by_title( $p['title'], OBJECT, 'post' );
	if ( $existing ) {
		echo "Exists: \"{$p['title']}\" (id={$existing->ID})\n";
		continue;
	}

	$term_ids = array();
	foreach ( $p['cats'] as $slug ) {
		if ( isset( $cat_ids[ $slug ] ) ) {
			$term_ids[] = $cat_ids[ $slug ];
		}
	}
	if ( empty( $term_ids ) ) {
		$term_ids[] = 1;
	}

	$post_id = wp_insert_post( array(
		'post_title'   => $p['title'],
		'post_content' => $p['content'],
		'post_excerpt' => $p['excerpt'],
		'post_status'  => 'publish',
		'post_author'  => 1,
		'post_type'    => 'post',
	) );

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		echo "FAILED: \"{$p['title']}\"\n";
		continue;
	}

	wp_set_post_terms( $post_id, $term_ids, 'category' );

	if ( ! empty( $p['image'] ) ) {
		$attachment_id = media_sideload_image( $p['image'], $post_id, $p['title'], 'id' );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
			echo "Created: \"{$p['title']}\" (id={$post_id}, image=yes)\n";
		} else {
			echo "Created: \"{$p['title']}\" (id={$post_id}, image=FAILED: {$attachment_id->get_error_message()})\n";
		}
	} else {
		echo "Created: \"{$p['title']}\" (id={$post_id})\n";
	}

	sleep( 1 );
}

echo "\n═══ Seeding Complete ═══\n";
echo "\nNext steps:\n";
echo "  1. Go to PressNative → App Settings and configure branding\n";
echo "  2. Go to PressNative → Layout Settings:\n";
echo "     • Set Hero Carousel category to \"featured\"\n";
echo "     • Enable all categories in Category List\n";
echo "  3. Verify the app preview looks correct\n";
echo "  4. Add the PressNative QR shortcode to key pages: [pressnative_qr]\n";
echo "\n";
