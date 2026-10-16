<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;

final class AccessController
{
    private const FLASH_KEY = 'admin_access';

    public function handle(): array
    {
        $users = ServiceFactory::users(\db());
        $flags = ServiceFactory::featureFlags(\db());
        $adminId = (int)$_SESSION['user_id'];
        if (\admin_current_role() !== 'owner') {
            throw new \RuntimeException('Owner access is required for role and feature-access changes.');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verify_csrf();
            $message = '';
            $error = '';

            if (\admin_require_password_from_post($error)) {
                $action = (string)($_POST['action'] ?? '');
                $targetName = trim((string)($_POST['username'] ?? ''));
                $target = $users->findCredentialsByUsername($targetName);

                if (!$target) {
                    $error = 'User not found.';
                } else {
                    $targetId = (int)$target['id'];
                    if ($action === 'set_role') {
                        $role = (string)($_POST['role'] ?? 'none');
                        if (!in_array($role, ['none','support','admin','owner'], true)) $role = 'none';
                        $db=\db();
                        $db->beginTransaction();
                        try {
                            $users->lockActiveAdminInvariant();
                            $fresh=$users->findForAdmin($targetId);
                            if(!$fresh){
                                $error='User not found.';
                            } else {
                                $currentRole = \admin_current_role($fresh);
                                if (!empty($fresh['is_active']) && $currentRole === 'owner' && $role !== 'owner' && $users->ownerCount() <= 1) {
                                    $error = 'You cannot demote the last active owner.';
                                } elseif ($targetId === $adminId && $role === 'none') {
                                    $error = 'You cannot remove your own admin access while signed in.';
                                } else {
                                    $users->setAdminRole($targetId, $role === 'none' ? null : $role);
                                    \admin_audit('admin_role_changed', $targetName . ' -> ' . $role, $targetId);
                                    $message = 'Admin role updated.';
                                }
                            }
                            if($error!=='')$db->rollBack();else$db->commit();
                        } catch (\Throwable $e) {
                            if($db->inTransaction())$db->rollBack();
                            throw $e;
                        }
                    } elseif ($action === 'set_flag') {
                        $flag = (string)($_POST['flag_key'] ?? '');
                        $enabled = !empty($_POST['enabled']);
                        $known = array_column($flags->all(), 'flag_key');
                        if (!in_array($flag, $known, true)) {
                            $error = 'Unknown feature flag.';
                        } else {
                            $flags->setForUser($targetId, $flag, $enabled);
                            \admin_audit('feature_flag_changed', $targetName . ' · ' . $flag . ' -> ' . ($enabled ? 'on' : 'off'), $targetId);
                            $message = 'Feature flag updated.';
                        }
                    } else {
                        $error = 'Unknown access action.';
                    }
                }
            }

            \web_post_redirect('admin/access.php', self::FLASH_KEY, $message, $error);
        }

        $flash = \web_flash_take(self::FLASH_KEY);
        return [
            'directory' => $users->adminDirectory(),
            'accessDirectory' => $users->accessDirectory(),
            'flags' => $flags->all(),
            'message' => $flash['message'],
            'error' => $flash['error'],
        ];
    }
}
