<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') · {{ $appSettings['council_name'] ?? 'SVMS' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/svms.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@php
    $me = auth()->user();
    $isAdmin = $me->isAdmin();
@endphp
<a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#main">Skip to main content</a>

<div class="app-shell">
    {{-- Sidebar: offcanvas on mobile/tablet, fixed on desktop --}}
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Main navigation">
        <div class="sidebar-brand d-flex justify-content-between align-items-start">
            <div>
                <div class="brand-title"><i class="bi bi-shield-check me-1"></i> SVMS</div>
                <div class="brand-sub">{{ $appSettings['council_name'] ?? 'Student Council' }}</div>
            </div>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
        </div>

        <div class="offcanvas-body">
            <nav class="nav flex-column" aria-label="Primary">
                <a @class(['nav-link', 'active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>

                @if($isAdmin)
                    <a @class(['nav-link', 'active' => request()->routeIs('records.*')]) href="{{ route('records.index') }}"><i class="bi bi-journal-text"></i> Violation Reports</a>
                @else
                    <a @class(['nav-link', 'active' => request()->routeIs('records.create')]) href="{{ route('records.create') }}"><i class="bi bi-plus-circle"></i> New Report</a>
                    <a @class(['nav-link', 'active' => request()->routeIs('records.index', 'records.show', 'records.edit')]) href="{{ route('records.index') }}"><i class="bi bi-journal-text"></i> My Reports</a>
                @endif

                <a @class(['nav-link', 'active' => request()->routeIs('students.*')]) href="{{ route('students.index') }}"><i class="bi bi-people"></i> Students</a>

                @if($isAdmin)
                    <a @class(['nav-link', 'active' => request()->routeIs('reports.*')]) href="{{ route('reports.index') }}"><i class="bi bi-bar-chart-line"></i> Reports &amp; Statistics</a>

                    <div class="nav-section">Configuration</div>
                    <a @class(['nav-link', 'active' => request()->routeIs('categories.*')]) href="{{ route('categories.index') }}"><i class="bi bi-tags"></i> Violation Categories</a>
                    <a @class(['nav-link', 'active' => request()->routeIs('actions.*')]) href="{{ route('actions.index') }}"><i class="bi bi-hammer"></i> Disciplinary Actions</a>
                    <a @class(['nav-link', 'active' => request()->routeIs('courses.*')]) href="{{ route('courses.index') }}"><i class="bi bi-mortarboard"></i> Courses &amp; Sections</a>

                    <div class="nav-section">Administration</div>
                    <a @class(['nav-link', 'active' => request()->routeIs('users.*')]) href="{{ route('users.index') }}"><i class="bi bi-person-badge"></i> Officer Accounts</a>
                    <a @class(['nav-link', 'active' => request()->routeIs('audit.*')]) href="{{ route('audit.index') }}"><i class="bi bi-clipboard-data"></i> Audit Logs</a>
                    <a @class(['nav-link', 'active' => request()->routeIs('settings.*')]) href="{{ route('settings.edit') }}"><i class="bi bi-gear"></i> Settings</a>
                @endif
            </nav>
        </div>
    </aside>

    <div class="app-main d-flex flex-column">
        <header class="topbar">
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-heading flex-grow-1">@yield('title', 'Dashboard')</h1>

            {{-- Notifications --}}
            <div class="dropdown">
                <button class="btn btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications ({{ $unreadCount }} unread)">
                    <i class="bi bi-bell"></i>
                    @if($unreadCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 22rem; max-width: 92vw;">
                    <div class="px-3 py-2 fw-semibold border-bottom">Notifications</div>
                    @forelse($recentNotifications as $n)
                        <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-wrap py-2 border-bottom">
                                <div class="fw-semibold small">{{ $n->data['title'] ?? 'Notification' }}</div>
                                <div class="small text-muted">{{ $n->data['message'] ?? '' }}</div>
                                <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                            </button>
                        </form>
                    @empty
                        <div class="px-3 py-3 text-muted small">You're all caught up.</div>
                    @endforelse
                    <a class="dropdown-item text-center small py-2" href="{{ route('notifications.index') }}">View all notifications</a>
                </div>
            </div>

            {{-- User menu --}}
            <div class="dropdown">
                <button class="btn btn-outline-secondary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i>
                    <span class="d-none d-md-inline">{{ $me->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2">
                        <div class="fw-semibold">{{ $me->name }}</div>
                        <div class="small text-muted">{{ $me->role_label }}</div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('password.edit') }}"><i class="bi bi-key me-2"></i>Change password</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main id="main" class="p-3 p-lg-4 flex-grow-1">
            @if(session('temp_password'))
                <div class="alert alert-warning" role="alert">
                    <strong>Temporary password for {{ session('temp_for') }}:</strong>
                    <code class="fs-6 user-select-all">{{ session('temp_password') }}</code>
                    <div class="small mt-1">Shown only once. Give it to the user privately; they must change it when they first sign in.</div>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            @endif

            @yield('content')
        </main>

        <footer class="px-4 py-3 border-top bg-white small text-muted">
            <i class="bi bi-lock me-1"></i>{{ $appSettings['report_footer'] ?? '' }}
            @if(!empty($appSettings['dpo_contact']))
                · Data Protection Officer: {{ $appSettings['dpo_contact'] }}
            @endif
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/svms.js') }}"></script>
@stack('scripts')
</body>
</html>
