<?php
/**
 * Indra Hotel - Contact Us & Guest Inquiries
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seo.php';

$pdo = getDB();
$prefillSubject = $_GET['subject'] ?? '';

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
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
                    <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Indra Hotel Phnom Penh</h2>
                    
                    <div class="space-y-4 text-sm text-stone-600">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">location_on</span>
                            <div>
                                <div class="font-bold text-stone-900">Hotel Location</div>
                                <div><?= HOTEL_ADDRESS_STREET ?>, <?= HOTEL_ADDRESS_DISTRICT ?>, <?= HOTEL_ADDRESS_CITY ?>, <?= HOTEL_ADDRESS_COUNTRY ?></div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">phone_in_talk</span>
                            <div>
                                <div class="font-bold text-stone-900">Telephone / WhatsApp</div>
                                <a href="tel:<?= HOTEL_PHONE_RAW ?>" class="hover:text-stone-900 text-[#343c0a] font-semibold"><?= HOTEL_PHONE ?></a>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">mail</span>
                            <div>
                                <div class="font-bold text-stone-900">Electronic Mail</div>
                                <a href="mailto:<?= HOTEL_EMAIL ?>" class="hover:text-stone-900 text-[#343c0a] font-semibold"><?= HOTEL_EMAIL ?></a>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[#4B5320] text-xl mt-0.5">schedule</span>
                            <div>
                                <div class="font-bold text-stone-900">Front Desk Hours</div>
                                <div>24 Hours / 7 Days a Week</div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-stone-100 flex items-center gap-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-stone-400">Social:</span>
                        <a href="<?= HOTEL_FACEBOOK ?>" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded bg-stone-100 hover:bg-[#4B5320] hover:text-white flex items-center justify-center text-xs font-bold transition">f</a>
                        <a href="<?= HOTEL_INSTAGRAM ?>" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded bg-stone-100 hover:bg-[#4B5320] hover:text-white flex items-center justify-center text-xs font-bold transition">ig</a>
                        <a href="<?= HOTEL_TRIPADVISOR ?>" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded bg-stone-100 hover:bg-[#4B5320] hover:text-white flex items-center justify-center text-xs transition">
                            <span class="material-symbols-outlined text-xs">star</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Inquiry Form Column -->
            <div class="lg:col-span-7 reveal reveal-right">
                <div class="bg-white rounded-2xl p-8 sm:p-10 border border-stone-200 shadow-sm space-y-6">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-[#4B5320] block mb-1">Send a Message</span>
                        <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">How May We Assist You?</h2>
                        <p class="text-xs text-stone-500 mt-1">Please provide your details below and our team will get back to you promptly.</p>
                    </div>

                    <form action="<?= url('/contact') ?>" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Your Full Name *</label>
                                <input type="text" name="name" required placeholder="e.g. Elena Rostova"
                                       class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Email Address *</label>
                                <input type="email" name="email" required placeholder="e.g. elena@example.com"
                                       class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Phone / WhatsApp</label>
                                <input type="text" name="phone" placeholder="e.g. +855 16 889 066"
                                       class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Subject</label>
                                <select name="subject" class="w-full text-sm border border-stone-300 rounded-lg p-2.5 focus:ring-2 focus:ring-[#343c0a]">
                                    <option value="General Inquiry" <?= $prefillSubject === '' ? 'selected' : '' ?>>General Inquiry</option>
                                    <option value="Suite Reservation" <?= $prefillSubject === 'Suite Reservation' ? 'selected' : '' ?>>Suite Reservation</option>
                                    <option value="Table Reservation" <?= $prefillSubject === 'Table Reservation' ? 'selected' : '' ?>>The Bistro Table Booking</option>
                                    <option value="Spa Booking" <?= $prefillSubject === 'Spa Booking' ? 'selected' : '' ?>>Spa Treatment Booking</option>
                                    <option value="Airport Transfer" <?= $prefillSubject === 'Airport Transfer' ? 'selected' : '' ?>>Airport Transfer Request</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">Your Message *</label>
                            <textarea name="message" rows="5" required placeholder="Tell us how we can make your stay unforgettable..."
                                      class="w-full text-sm border border-stone-300 rounded-lg p-3 focus:ring-2 focus:ring-[#343c0a]"></textarea>
                        </div>

                        <button type="submit" class="w-full bg-[#343c0a] hover:bg-deep-olive text-white py-3.5 rounded-lg font-bold text-sm tracking-wide transition shadow flex items-center justify-center gap-2 cursor-pointer btn-shimmer">
                            <span class="material-symbols-outlined text-lg">send</span>
                            <span>Send Message to Concierge</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
