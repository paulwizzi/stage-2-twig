<?php
declare(strict_types=1);

namespace App;

final class Store
{
    private string $dir;

    public function __construct(string $storageDir)
    {
        $this->dir = rtrim($storageDir, '/');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    private function readJson(string $filename): array
    {
        $path = $this->dir . '/' . $filename;
        if (!file_exists($path)) return [];
        $raw = file_get_contents($path);
        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    private function writeJson(string $filename, array $data): void
    {
        $path = $this->dir . '/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function replaceUsers(array $users): void
    {
        $sanitized = [];
        foreach ($users as $u) {
            $email = (string)($u['email'] ?? '');
            $password = (string)($u['password'] ?? '');
            if ($email !== '' && $password !== '') {
                $sanitized[] = [ 'email' => $email, 'password' => $password ];
            }
        }
        $this->writeJson('users.json', $sanitized);
    }

    public function replaceTickets(array $tickets): void
    {
        $sanitized = [];
        foreach ($tickets as $t) {
            $id = (int)($t['id'] ?? 0);
            $userEmail = (string)($t['userEmail'] ?? '');
            $title = (string)($t['title'] ?? '');
            $status = (string)($t['status'] ?? 'open');
            $description = (string)($t['description'] ?? '');
            $priority = (string)($t['priority'] ?? 'normal');
            if ($id > 0 && $userEmail !== '' && $title !== '') {
                $sanitized[] = compact('id','userEmail','title','status','description','priority');
            }
        }
        $this->writeJson('tickets.json', $sanitized);
    }

    public function registerUser(string $email, string $password): array
    {
        $users = $this->readJson('users.json');
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email) {
                return [ 'success' => false, 'error' => 'This email is already registered. Please login instead.' ];
            }
        }
        $users[] = [ 'email' => $email, 'password' => $password ];
        $this->writeJson('users.json', $users);
        return [ 'success' => true ];
    }

    public function validateUser(string $email, string $password): ?array
    {
        $users = $this->readJson('users.json');
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email && ($u['password'] ?? '') === $password) {
                return $u;
            }
        }
        return null;
    }

    public function getTickets(string $userEmail): array
    {
        $all = $this->readJson('tickets.json');
        $out = [];
        foreach ($all as $t) {
            if (($t['userEmail'] ?? '') === $userEmail) $out[] = $t;
        }
        return $out;
    }

    public function createTicket(string $userEmail, string $title, string $status, string $description): void
    {
        $all = $this->readJson('tickets.json');
        $all[] = [
            'id' => (int) round(microtime(true) * 1000),
            'userEmail' => $userEmail,
            'title' => $title,
            'status' => $status,
            'description' => $description,
            'priority' => 'normal',
        ];
        $this->writeJson('tickets.json', $all);
    }

    public function updateTicket(string $userEmail, int $id, string $title, string $status, string $description): void
    {
        $all = $this->readJson('tickets.json');
        foreach ($all as &$t) {
            if (($t['id'] ?? 0) === $id && ($t['userEmail'] ?? '') === $userEmail) {
                $t['title'] = $title;
                $t['status'] = $status;
                $t['description'] = $description;
            }
        }
        $this->writeJson('tickets.json', $all);
    }

    public function deleteTicket(string $userEmail, int $id): void
    {
        $all = $this->readJson('tickets.json');
        $all = array_values(array_filter($all, fn($t) => ($t['id'] ?? 0) !== $id || ($t['userEmail'] ?? '') !== $userEmail));
        $this->writeJson('tickets.json', $all);
    }
}


