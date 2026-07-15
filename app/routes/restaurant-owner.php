<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'];
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($uri === '/moj-meni/prijava') {
    if ($method === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!RateLimiter::hit(RateLimiter::clientKey('owner-login:' . $email), 10, 900)) {
            flash('error', 'Previše pokušaja. Sačekajte 15 minuta.');
            redirect('/moj-meni/prijava');
        }
        if (Auth::attemptOwner($email, $_POST['password'] ?? '')) {
            redirect($_GET['return'] ?? '/moj-meni');
        }
        flash('error', 'Pogrešan email ili lozinka.');
    }
    if (Auth::isOwner()) {
        redirect('/moj-meni');
    }
    view('meni/login', ['title' => 'Prijava vlasnika'], null);
    exit;
}

if ($uri === '/moj-meni/registracija') {
    if ($method === 'POST') {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!RateLimiter::hit(RateLimiter::clientKey('owner-reg:' . $email), 5, 3600)) {
            flash('error', 'Previše pokušaja registracije.');
            redirect('/moj-meni/registracija');
        }
        $id = Auth::registerOwner($email, $_POST['password'] ?? '', (string) ($_POST['name'] ?? ''));
        if (!$id) {
            flash('error', 'Email je zauzet ili lozinka je prekratka (min. 8 znakova).');
            redirect('/moj-meni/registracija');
        }
        Auth::attemptOwner($email, $_POST['password'] ?? '');
        flash('success', 'Dobrodošli! Popunite profil vašeg restorana.');
        redirect('/moj-meni/profil');
    }
    if (Auth::isOwner()) {
        redirect('/moj-meni');
    }
    view('meni/register', ['title' => 'Registracija restorana'], null);
    exit;
}

if ($uri === '/moj-meni/odjava') {
    Auth::logout();
    redirect('/moj-meni/prijava');
}

Auth::requireOwner();
$user = Auth::user();
$restaurant = RestaurantRepository::getByOwnerId($user['id']);

if ($uri === '/moj-meni/upload' && $method === 'POST') {
    verify_csrf();
    $file = get_uploaded_image_file();
    if (!$file || !is_valid_upload_image($file) || $file['size'] > config('upload_max_bytes')) {
        json_response(['error' => 'Nevaljana slika.'], 400);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = 'rest-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = config('upload_dir') . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_response(['error' => 'Upload nije uspio.'], 500);
    }
    ImageProcessor::process($dest);
    json_response(['url' => '/uploads/' . $name]);
}

if ($uri === '/moj-meni' || $uri === '/moj-meni/') {
    meni_view('dashboard', [
        'title' => 'Moj digitalni meni',
        'active' => 'dashboard',
        'user' => $user,
        'restaurant' => $restaurant,
    ]);
    exit;
}

if ($uri === '/moj-meni/profil') {
    if ($method === 'POST') {
        verify_csrf();
        $hours = [];
        foreach (['pon', 'uto', 'sri', 'cet', 'pet', 'sub', 'ned'] as $d) {
            $hours[$d] = trim((string) ($_POST['hours_' . $d] ?? ''));
        }
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'city' => $_POST['city'] ?? 'NOVI_PAZAR',
            'address' => trim((string) ($_POST['address'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'whatsapp' => trim((string) ($_POST['whatsapp'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'logoImage' => trim((string) ($_POST['logoImage'] ?? '')) ?: null,
            'coverImage' => trim((string) ($_POST['coverImage'] ?? '')) ?: null,
            'reviewsEnabled' => isset($_POST['reviewsEnabled']) ? 1 : 0,
            'hours' => $hours,
            'menuLangs' => MenuI18n::menuLangsFromPost($_POST),
        ];
        if ($data['name'] === '') {
            flash('error', 'Naziv restorana je obavezan.');
            redirect('/moj-meni/profil');
        }
        $id = RestaurantService::saveRestaurant($data, $user['id'], $restaurant['id'] ?? null);
        if (isset($_POST['submit_review'])) {
            RestaurantService::submitForReview($id, $user['id']);
            flash('success', 'Profil sačuvan i poslan na odobrenje.');
        } else {
            flash('success', 'Profil sačuvan.');
        }
        redirect('/moj-meni/profil');
    }
    meni_view('profile', [
        'title' => 'Profil restorana',
        'active' => 'profil',
        'user' => $user,
        'restaurant' => $restaurant,
    ]);
    exit;
}

if ($uri === '/moj-meni/meni') {
    if (!$restaurant) {
        flash('error', 'Prvo popunite profil restorana.');
        redirect('/moj-meni/profil');
    }
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['add_category'])) {
            RestaurantService::saveCategory($restaurant['id'], (string) ($_POST['category_name'] ?? ''));
            flash('success', 'Kategorija dodana.');
        }
        if (isset($_POST['delete_category'])) {
            RestaurantService::deleteCategory($restaurant['id'], (string) ($_POST['category_id'] ?? ''));
            flash('success', 'Kategorija obrisana.');
        }
        redirect('/moj-meni/meni');
    }
    $categories = RestaurantRepository::getFullMenu($restaurant['id']);
    meni_view('menu', [
        'title' => 'Cjenovnik',
        'active' => 'meni',
        'user' => $user,
        'restaurant' => $restaurant,
        'categories' => $categories,
    ]);
    exit;
}

if (preg_match('#^/moj-meni/stavka(?:/([^/]+))?$#', $uri, $m)) {
    if (!$restaurant) {
        redirect('/moj-meni/profil');
    }
    $itemId = $m[1] ?? null;
    $item = null;
    if ($itemId) {
        foreach (RestaurantRepository::getMenuItems($restaurant['id']) as $row) {
            if ($row['id'] === $itemId) {
                $item = $row;
                break;
            }
        }
        if (!$item) {
            not_found();
        }
    }
    if ($method === 'POST') {
        verify_csrf();
        if (isset($_POST['delete'])) {
            RestaurantService::deleteMenuItem($restaurant['id'], $itemId);
            flash('success', 'Stavka obrisana.');
            redirect('/moj-meni/meni');
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? '')))));
        $data = [
            'categoryId' => $_POST['categoryId'] ?? '',
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'price' => $_POST['price'] ?? '',
            'priceLabel' => trim((string) ($_POST['priceLabel'] ?? '')),
            'currency' => $_POST['currency'] ?? 'RSD',
            'image' => trim((string) ($_POST['image'] ?? '')) ?: null,
            'tags' => $tags,
            'translations' => MenuI18n::translationsFromPost($_POST),
            'isAvailable' => isset($_POST['isAvailable']) ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['categoryId'] === '') {
            flash('error', 'Naziv i kategorija su obavezni.');
            redirect($uri);
        }
        RestaurantService::saveMenuItem($restaurant['id'], $data, $itemId);
        flash('success', 'Stavka sačuvana.');
        redirect('/moj-meni/meni');
    }
    $categories = RestaurantRepository::getMenuCategories($restaurant['id']);
    meni_view('item-form', [
        'title' => $item ? 'Uredi stavku' : 'Nova stavka',
        'active' => 'meni',
        'user' => $user,
        'restaurant' => $restaurant,
        'item' => $item,
        'categories' => $categories,
    ]);
    exit;
}

if ($uri === '/moj-meni/qr.png') {
    if (!$restaurant) {
        redirect('/moj-meni/profil');
    }
    RestaurantService::outputQrPng($restaurant);
}

if ($uri === '/moj-meni/meni/skeniraj' && $method === 'POST') {
    if (!$restaurant) {
        redirect('/moj-meni/profil');
    }
    verify_csrf();
    $file = get_uploaded_image_file();
    if (!$file || !is_valid_upload_image($file) || $file['size'] > config('upload_max_bytes')) {
        flash('error', 'Učitajte jasnu fotografiju menija (JPG/PNG, max 5 MB).');
        redirect('/moj-meni/meni');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $tmp = config('upload_dir') . '/scan-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tmp)) {
        flash('error', 'Upload nije uspio.');
        redirect('/moj-meni/meni');
    }
    $result = RestaurantMenuScan::extractFromImagePath($tmp);
    @unlink($tmp);
    if (is_string($result)) {
        flash('error', $result);
        redirect('/moj-meni/meni');
    }
    $replace = isset($_POST['replace_menu']);
    $n = RestaurantService::importScannedMenu($restaurant['id'], $result, $replace);
    $note = trim((string) ($result['notes'] ?? ''));
    flash('success', 'Uvezeno ' . $n . ' stavki.' . ($note ? ' ' . $note : ''));
    redirect('/moj-meni/meni');
}

if ($uri === '/moj-meni/qr') {
    if (!$restaurant) {
        redirect('/moj-meni/profil');
    }
    meni_view('qr', [
        'title' => 'QR kod menija',
        'active' => 'qr',
        'user' => $user,
        'restaurant' => $restaurant,
        'publicUrl' => RestaurantService::publicUrl($restaurant),
        'qrUrl' => RestaurantService::qrImageUrl($restaurant, 500),
        'shortUrl' => config('site_url') . '/r/' . $restaurant['qrCode'],
        'downloadUrl' => '/moj-meni/qr.png',
    ]);
    exit;
}

not_found();
