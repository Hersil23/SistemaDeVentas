<?php
$pageTitle = 'Bienvenido';
require_once 'includes/header.php';
?>

<!-- Navbar Landing -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-dark-card/80 backdrop-blur-md border-b border-light-border dark:border-dark-border">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- Logo -->
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-slate-800 dark:text-white">SistemaDeVentas</span>
            </div>
            
            <!-- Botones -->
            <div class="flex items-center gap-3">
                <!-- Toggle Dark Mode -->
                <button onclick="toggleDarkMode()" 
                        class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors"
                        title="Cambiar tema">
                    <!-- Sol (modo claro) -->
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <!-- Luna (modo oscuro) -->
                    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
                
                <!-- Botón Iniciar Sesión -->
                <a href="login.php" 
                   class="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Iniciar Sesion
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="min-h-screen flex items-center justify-center pt-16 relative overflow-hidden">
    
    <!-- Gradiente de fondo -->
    <div class="absolute inset-0 bg-gradient-to-br from-primary-500/10 via-transparent to-green-500/10 dark:from-primary-500/20 dark:to-green-500/20"></div>
    
    <!-- Formas decorativas -->
    <div class="absolute top-20 left-10 w-72 h-72 bg-primary-500/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-20 right-10 w-96 h-96 bg-green-500/10 rounded-full blur-3xl"></div>
    
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
            
            <!-- Titulo principal -->
            <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-slate-800 dark:text-white mb-6">
                Control total de 
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-500 to-primary-700 dark:from-primary-400 dark:to-primary-600">
                    tu negocio
                </span>
            </h1>
            
            <!-- Subtitulo -->
            <p class="text-lg sm:text-xl text-slate-600 dark:text-slate-300 mb-10 max-w-2xl mx-auto">
                Administra cuentas, perfiles, clientes y ventas desde un solo lugar. 
                Simple, rapido y eficiente.
            </p>
            
            <!-- Botón CTA -->
            <a href="login.php" 
               class="inline-flex items-center gap-2 px-6 py-3 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-xl shadow-lg shadow-primary-500/30 hover:shadow-primary-500/40 transition-all duration-300 transform hover:-translate-y-1">
                Acceder al Sistema
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
            
            <!-- Iconos de funciones -->
            <div class="mt-16 grid grid-cols-3 sm:grid-cols-6 gap-4 sm:gap-6 max-w-3xl mx-auto">
                
                <!-- Usuarios -->
                <div class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/50 dark:bg-slate-800/50 backdrop-blur-sm border border-light-border dark:border-dark-border hover:border-primary-300 dark:hover:border-primary-600 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Usuarios</span>
                </div>
                
                <!-- Cuentas -->
                <div class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/50 dark:bg-slate-800/50 backdrop-blur-sm border border-light-border dark:border-dark-border hover:border-primary-300 dark:hover:border-primary-600 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Cuentas</span>
                </div>
                
                <!-- Ventas -->
                <div class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/50 dark:bg-slate-800/50 backdrop-blur-sm border border-light-border dark:border-dark-border hover:border-primary-300 dark:hover:border-primary-600 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Ventas</span>
                </div>
                
                <!-- Reportes -->
                <div class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/50 dark:bg-slate-800/50 backdrop-blur-sm border border-light-border dark:border-dark-border hover:border-primary-300 dark:hover:border-primary-600 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Reportes</span>
                </div>
                
                <!-- Alertas -->
                <div class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/50 dark:bg-slate-800/50 backdrop-blur-sm border border-light-border dark:border-dark-border hover:border-primary-300 dark:hover:border-primary-600 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Alertas</span>
                </div>
                
                <!-- WhatsApp -->
                <div class="flex flex-col items-center gap-2 p-4 rounded-xl bg-white/50 dark:bg-slate-800/50 backdrop-blur-sm border border-light-border dark:border-dark-border hover:border-primary-300 dark:hover:border-primary-600 transition-colors">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-500">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-400">WhatsApp</span>
                </div>
                
            </div>
            
        </div>
    </div>
</section>

<!-- Sección Características -->
<section class="py-16 sm:py-24 bg-white dark:bg-dark-card">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Titulo sección -->
        <div class="text-center mb-12">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white mb-4">
                Todo lo que necesitas para gestionar tu negocio
            </h2>
            <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                Un sistema completo diseñado para simplificar la administracion de tus ventas
            </p>
        </div>
        
        <!-- Grid de características -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
            
            <!-- Característica 1 -->
            <div class="p-6 rounded-2xl bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 dark:text-white mb-2">Gestion de Cuentas</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm">
                    Administra cuentas y perfiles con control de inventario en tiempo real. 
                    Visualiza disponibilidad al instante.
                </p>
            </div>
            
            <!-- Característica 2 -->
            <div class="p-6 rounded-2xl bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-500 mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 dark:text-white mb-2">Control de Ventas</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm">
                    Registra ventas con duracion flexible: 1, 3, 6 o 12 meses. 
                    Calcula ganancias automaticamente.
                </p>
            </div>
            
            <!-- Característica 3 -->
            <div class="p-6 rounded-2xl bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-500 mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 dark:text-white mb-2">Reportes y Cierres</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm">
                    Cierres mensuales automaticos con desglose por vendedor. 
                    Estadisticas claras y precisas.
                </p>
            </div>
            
            <!-- Característica 4 -->
            <div class="p-6 rounded-2xl bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-500 mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 dark:text-white mb-2">Alertas de Vencimiento</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm">
                    Recibe alertas de servicios proximos a vencer. 
                    Nunca pierdas una renovacion.
                </p>
            </div>
            
            <!-- Característica 5 -->
            <div class="p-6 rounded-2xl bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-500 mb-4">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 dark:text-white mb-2">Integracion WhatsApp</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm">
                    Envia recordatorios de cobro directamente por WhatsApp con mensajes predefinidos.
                </p>
            </div>
            
            <!-- Característica 6 -->
            <div class="p-6 rounded-2xl bg-light-bg dark:bg-dark-bg border border-light-border dark:border-dark-border hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-500 mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 dark:text-white mb-2">Multi-moneda</h3>
                <p class="text-slate-600 dark:text-slate-400 text-sm">
                    Trabaja con USD, COP, PEN, BOB y VES. 
                    Tasas de cambio via API o manuales.
                </p>
            </div>
            
        </div>
    </div>
</section>

<!-- CTA Final -->
<section class="py-16 sm:py-20 relative overflow-hidden bg-light-bg dark:bg-dark-bg">
    <div class="absolute inset-0 bg-gradient-to-br from-primary-500/10 via-transparent to-green-500/10 dark:from-primary-500/20 dark:to-green-500/20"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white mb-4">
            Comienza a gestionar tu negocio hoy
        </h2>
        <p class="text-slate-600 dark:text-slate-300 mb-8 max-w-xl mx-auto">
            Accede al sistema y toma el control total de tus ventas, clientes y ganancias.
        </p>
        <a href="login.php" 
           class="inline-flex items-center gap-2 px-8 py-4 bg-white hover:bg-slate-100 text-primary-600 font-semibold rounded-xl shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            Iniciar Sesion
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
        </a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>