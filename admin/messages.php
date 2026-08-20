<?php
/**
 * Indra Hotel - Guest Inquiries Inbox CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Guest Inquiries & Messages';

// Handle Mark Read / Replied / Delete
if (isset($_GET['action'])) {
    $msgId = (int)($_GET['id'] ?? 0);
    if ($_GET['action'] === 'read' && $msgId > 0) {
        $pdo->prepare("UPDATE messages SET status = 'read' WHERE id = ?")->execute([$msgId]);
        set_flash('success', 'Message marked as read.');
    } elseif ($_GET['action'] === 'delete' && $msgId > 0) {
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$msgId]);
        set_flash('success', 'Message deleted.');
    }
    header('Location: ' . BASE_URL . '/admin/messages.php');
    exit;
}

$stmtMsgs = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC");
$messages = $stmtMsgs->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">
    
    <div>
        <h2 class="font-headline text-2xl font-bold text-onyx-charcoal">Guest Inquiries Inbox</h2>
        <p class="text-xs text-stone-500">Submissions received via public Contact Us form.</p>
    </div>

    <!-- Messages List -->
    <div class="space-y-4">
        <?php if (empty($messages)): ?>
        <div class="bg-white rounded-2xl p-12 text-center text-stone-400 border border-stone-200">
            <span class="material-symbols-outlined text-4xl mb-2 text-stone-300">mail_outline</span>
            <p>Your inbox is empty. No guest inquiries at the moment.</p>
        </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
            <div class="bg-white rounded-2xl p-6 border <?= $msg['status'] === 'unread' ? 'border-[#343c0a] bg-stone-50/50 shadow-sm' : 'border-stone-200 shadow-xs' ?> space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-stone-100">
                    <div class="flex items-center gap-3">
                        <?php if ($msg['status'] === 'unread'): ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <?php endif; ?>
                        <div>
                            <span class="font-headline font-bold text-base text-onyx-charcoal"><?= e($msg['name']) ?></span>
                            <span class="text-xs text-stone-400 ml-2">&lt;<?= e($msg['email']) ?>&gt; <?= !empty($msg['phone']) ? '• ' . e($msg['phone']) : '' ?></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-stone-400">
                        <span><?= format_date($msg['created_at'], 'M d, Y H:i') ?></span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $msg['status'] === 'unread' ? 'bg-rose-100 text-rose-800' : 'bg-stone-100 text-stone-600' ?>">
                            <?= e($msg['status']) ?>
                        </span>
                    </div>
                </div>

                <div>
                    <div class="font-headline font-semibold text-sm text-stone-800 mb-1">Subject: <?= e($msg['subject']) ?></div>
                    <p class="text-xs text-stone-600 leading-relaxed whitespace-pre-line bg-stone-50 p-4 rounded-xl border border-stone-100">
                        <?= e($msg['message']) ?>
                    </p>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="mailto:<?= e($msg['email']) ?>?subject=<?= urlencode('Re: ' . $msg['subject'] . ' - Indra Hotel') ?>" class="bg-[#343c0a] hover:bg-deep-olive text-white px-4 py-1.5 rounded text-xs font-semibold transition flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm">reply</span>
                        <span>Reply by Email</span>
                    </a>

                    <div class="space-x-2 text-xs">
                        <?php if ($msg['status'] === 'unread'): ?>
                        <a href="<?= BASE_URL ?>/admin/messages.php?action=read&id=<?= $msg['id'] ?>" class="text-stone-600 hover:text-stone-900 font-medium">Mark Read</a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/admin/messages.php?action=delete&id=<?= $msg['id'] ?>" onclick="return confirm('Delete message?')" class="text-rose-600 hover:text-rose-800 font-medium">Delete</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
