<?php
/**
 * Indra Hotel - Contact Us & Guest Inquiries
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/google-auth.php';

$pdo = getDB();
$isGuest = is_guest_logged_in();
$guest = get_logged_in_guest();
$googleAuthEnabled = GoogleAuth::isEnabled();
$prefillSubject = $_GET['subject'] ?? '';

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($googleAuthEnabled && !$isGuest) {
        set_flash('error', 'Authentication required. Please sign in with Google to send a message.');
        header('Location: ' . url('/contact'));
        exit;
    }

    $name = $isGuest ? $guest['name'] : trim($_POST['name'] ?? '');
    $email = $isGuest ? $guest['email'] : trim($_POST['email'] ?? '');

    if (!empty($email) && is_guest_restricted($email)) {
        set_flash('error', 'Your guest account has been restricted by hotel management from sending messages.');
        header('Location: ' . url('/contact'));
        exit;
    }
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? 'General Inquiry');
    $message = trim($_POST['message'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!Auth::verifyCsrf($csrf)) {
        set_flash('error', 'Security token expired. Please try submitting again.');
    } elseif (empty($name) || empty($email) || empty($message)) {
        set_flash('error', 'Please fill in all required fields (Name, Email, Message).');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Please provide a valid email address.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'unread')");
            $stmt->execute([$name, $email, $phone, $subject, $message]);

            // Dispatch Automated Transactional Emails
            require_once __DIR__ . '/includes/mailer.php';
            $contactData = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message
            ];

            try {
                Mailer::sendContactNotificationToHotel($contactData);
            } catch (Throwable $t) {}

            try {
                Mailer::sendContactAutoReply($contactData);
            } catch (Throwable $t) {}

            set_flash('success', 'Thank you! Your message has been received. Our concierge team will respond promptly, and an email acknowledgment has been sent.');
            header('Location: ' . url('/contact'));
            exit;
        } catch (Throwable $e) {
            set_flash('error', 'Unable to submit your message right now. Please call us directly.');
        }
    }
}

$pageMeta = [
    'title' => 'Contact Us | Indra Hotel Phnom Penh',
    'description' => 'Get in touch with Indra Hotel Phnom Penh. Inquiries for suite reservations, table bookings, spa appointments, and airport transfers.',
    'keywords' => 'contact indra hotel, indra hotel phone number, indra hotel email, book room phnom penh',
    'type' => 'website'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<?= render_public_page_hero('contact', [
    'badge' => 'We Are Here For You',
    'title' => 'Contact Sanctuary',
    'subtitle' => 'Have a question regarding reservations, dining, or special requests? Reach out to our dedicated concierge.',
    'image' => 'https://images.unsplash.com/photo-1596524430615-b46475ddff6e?auto=format&fit=crop&w=1600&q=80',
    'icon' => 'mail'
]) ?>


<!-- Contact Info & Interactive Form Section -->
<section class="py-16 bg-[#f9f9f9]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            
            <!-- Contact Information Column -->
            <div class="lg:col-span-5 space-y-6 reveal reveal-left">
                <div class="bg-white rounded-2xl p-8 border border-stone-200 shadow-sm space-y-6">
                    <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320] block">Direct Contact</span>
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal"><?= e(hotel_name()) ?></h2>
                    
                    <div class="space-y-4 text-sm text-stone-600">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">location_on</span>
                            <div>
                                <div class="font-bold text-stone-900">Hotel Location</div>
                                <div><?= e(hotel_address()) ?></div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">phone_in_talk</span>
                            <div>
                                <div class="font-bold text-stone-900">Telephone / WhatsApp</div>
                                <a href="tel:<?= preg_replace('/[^0-9\+]/', '', hotel_phone()) ?>" class="hover:text-stone-900 text-[#343c0a] font-semibold"><?= e(hotel_phone()) ?></a>
                                <?php if ($wa = hotel_whatsapp()): ?>
                                    <div class="text-xs text-stone-500 mt-0.5">
                                        WhatsApp: <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $wa) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-stone-900 text-[#343c0a] font-semibold"><?= e($wa) ?></a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($email = hotel_email()): ?>
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">mail</span>
                            <div>
                                <div class="font-bold text-stone-900">Electronic Mail</div>
                                <a href="mailto:<?= e($email) ?>" class="hover:text-stone-900 text-[#343c0a] font-semibold"><?= e($email) ?></a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">schedule</span>
                            <div>
                                <div class="font-bold text-stone-900">Front Desk Hours</div>
                                <div>24 Hours / 7 Days a Week</div>
                                <div class="text-xs text-stone-500 mt-0.5">Check-in: <?= e(get_setting('hotel_checkin_time', '14:00')) ?> | Check-out: <?= e(get_setting('hotel_checkout_time', '12:00')) ?></div>
                            </div>
                        </div>
                    </div>

                    <?php 
                    $fb = get_setting('social_facebook', HOTEL_FACEBOOK);
                    $ig = get_setting('social_instagram', HOTEL_INSTAGRAM);
                    $ta = get_setting('social_tripadvisor', HOTEL_TRIPADVISOR);
                    $tg = get_setting('social_telegram');
                    if ($fb || $ig || $ta || $tg): 
                    ?>
                    <div class="pt-6 border-t border-stone-100 flex items-center gap-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-stone-400">Social:</span>
                        <?php if ($fb): ?>
                            <a href="<?= e($fb) ?>" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded bg-stone-100 hover:bg-[#4B5320] hover:text-white flex items-center justify-center text-xs font-bold transition">f</a>
                        <?php endif; ?>
                        <?php if ($ig): ?>
                            <a href="<?= e($ig) ?>" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded bg-stone-100 hover:bg-[#4B5320] hover:text-white flex items-center justify-center text-xs font-bold transition">ig</a>
                        <?php endif; ?>
                        <?php if ($ta): ?>
                            <a href="<?= e($ta) ?>" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded bg-stone-100 hover:bg-[#4B5320] hover:text-white flex items-center justify-center text-xs transition" aria-label="Tripadvisor">
                                <span class="material-symbols-outlined text-xs">star</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($tg): ?>
                            <a href="<?= e($tg) ?>" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded bg-stone-100 hover:bg-[#4B5320] hover:text-white flex items-center justify-center text-xs transition" aria-label="Telegram">
                                <span class="material-symbols-outlined text-xs">send</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Inquiry Form Column -->
            <div class="lg:col-span-7 reveal reveal-right">
                <div class="bg-white rounded-2xl p-8 sm:p-10 border border-stone-200 shadow-sm space-y-6">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320] block mb-1"><?= __t('nav_contact', 'Contact') ?></span>
                        <h2 class="font-headline text-2xl font-bold text-onyx-charcoal"><?= __t('public_contact_title', 'Contact Indra Hotel') ?></h2>
                        <p class="text-xs text-stone-500 mt-1"><?= __t('public_contact_sub', 'We would love to hear from you. Please fill out the form below.') ?></p>
                    </div>

                    <?php if ($isGuest && is_guest_restricted($guest['email'])): ?>
                        <!-- Account Restricted Warning Card -->
                        <div class="p-8 rounded-2xl bg-rose-50 border border-rose-200 text-center space-y-4">
                            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center mx-auto">
                                <span class="material-symbols-outlined text-2xl">block</span>
                            </div>
                            <div>
                                <h3 class="font-headline font-bold text-lg text-rose-900">Message Privileges Restricted</h3>
                                <p class="text-xs text-rose-700 mt-1 max-w-md mx-auto">Your account (<strong><?= e($guest['email']) ?></strong>) has been restricted by hotel management. You cannot submit contact messages through the website. Please contact our front desk directly by telephone.</p>
                            </div>
                        </div>
                    <?php elseif ($googleAuthEnabled && !$isGuest): ?>
                        <!-- Anti-Spam Google Sign-In Required Card -->
                        <div class="p-6 rounded-2xl bg-stone-50 border border-stone-200 text-center space-y-4">
                            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-800 flex items-center justify-center mx-auto">
                                <span class="material-symbols-outlined text-2xl">verified_user</span>
                            </div>
                            <div>
                                <h3 class="font-headline font-bold text-lg text-onyx-charcoal">Sign In Required to Send Message</h3>
                                <p class="text-xs text-stone-500 mt-1 max-w-md mx-auto">To protect our concierge desk from automated spam, please sign in with your Google account before submitting a contact inquiry.</p>
                            </div>
                            <a href="<?= e(GoogleAuth::getGuestAuthUrl(url('/contact'))) ?>" class="inline-flex items-center justify-center gap-2.5 bg-white hover:bg-stone-100 text-stone-800 border border-stone-300 font-bold px-6 py-3 rounded-xl text-xs sm:text-sm tracking-wide transition shadow-xs cursor-pointer btn-shimmer">
                                <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                <span>Sign in with Google to Contact</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Verified Guest Identity Badge -->
                        <?php if ($isGuest): ?>
                        <div class="flex items-center justify-between p-3.5 bg-stone-50 border border-stone-200 rounded-xl">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($guest['picture'])): ?>
                                    <img src="<?= e($guest['picture']) ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover shadow-2xs">
                                <?php else: ?>
                                    <div class="w-9 h-9 rounded-full bg-[#343c0a] text-white flex items-center justify-center font-bold text-sm">
                                        <?= strtoupper(substr($guest['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="text-xs font-bold text-stone-900 flex items-center gap-1.5">
                                        <span><?= e($guest['name']) ?></span>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-800 bg-emerald-100/80 px-1.5 py-0.5 rounded-md">
                                            <span class="material-symbols-outlined text-[12px]">verified</span> Verified
                                        </span>
                                    </div>
                                    <div class="text-xs text-stone-500"><?= e($guest['email']) ?></div>
                                </div>
                            </div>
                            <a href="<?= url('/api/guest-logout.php', ['redirect' => url('/contact')]) ?>" class="text-[11px] font-semibold text-stone-500 hover:text-stone-900 underline">Sign Out</a>
                        </div>
                        <?php endif; ?>

                        <form action="<?= url('/contact') ?>" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1"><?= __t('public_contact_name', 'Your Full Name') ?> *</label>
                                    <input type="text" name="name" value="<?= e($isGuest ? $guest['name'] : '') ?>" <?= $isGuest ? 'readonly class="w-full text-sm border border-stone-300 rounded-lg p-2.5 bg-stone-100 cursor-not-allowed font-medium text-stone-700"' : 'required class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"' ?>>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1"><?= __t('public_contact_email', 'Email Address') ?> *</label>
                                    <input type="email" name="email" value="<?= e($isGuest ? $guest['email'] : '') ?>" <?= $isGuest ? 'readonly class="w-full text-sm border border-stone-300 rounded-lg p-2.5 bg-stone-100 cursor-not-allowed font-medium text-stone-700"' : 'required class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"' ?>>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1"><?= __t('booking_phone', 'Mobile / WhatsApp') ?></label>
                                    <input type="text" name="phone" placeholder="e.g. +855 16 889 066"
                                           class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1"><?= __t('public_contact_subject', 'Subject') ?></label>
                                    <select name="subject" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                                        <option value="General Inquiry" <?= $prefillSubject === 'General Inquiry' ? 'selected' : '' ?>>General Inquiry</option>
                                        <option value="Room Reservation" <?= $prefillSubject === 'Room Reservation' ? 'selected' : '' ?>>Room Reservation Inquiry</option>
                                        <option value="Dining Reservation" <?= $prefillSubject === 'Dining Reservation' ? 'selected' : '' ?>>Dining & Bistro Reservation</option>
                                        <option value="Spa Appointment" <?= $prefillSubject === 'Spa Appointment' ? 'selected' : '' ?>>Spa & Wellness Appointment</option>
                                        <option value="Airport Transfer" <?= $prefillSubject === 'Airport Transfer' ? 'selected' : '' ?>>Airport Transfer Request</option>
                                        <option value="Feedback" <?= $prefillSubject === 'Feedback' ? 'selected' : '' ?>>Feedback & Guest Experience</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1"><?= __t('public_contact_message', 'Your Message') ?> *</label>
                                <textarea name="message" rows="4" required placeholder="How can our team assist you today?"
                                          class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]"></textarea>
                            </div>

                            <button type="submit" class="w-full bg-[#343c0a] hover:bg-deep-olive text-white font-bold py-3 px-6 rounded-lg text-sm tracking-wide transition shadow flex items-center justify-center gap-2 btn-shimmer cursor-pointer">
                                <span class="material-symbols-outlined text-lg">send</span>
                                <span><?= __t('public_contact_send_btn', 'Send Message') ?></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
