<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Ventas - Gestión de cuentas y perfiles">
    <meta name="theme-color" content="#3B82F6">
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>Sistema de Ventas</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Configuración de Tailwind con colores personalizados -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        // Colores primarios
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        // Fondos modo oscuro
                        dark: {
                            bg: '#0f172a',
                            card: '#1e293b',
                            border: '#334155',
                        },
                        // Fondos modo claro
                        light: {
                            bg: '#f8fafc',
                            card: '#ffffff',
                            border: '#e2e8f0',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Estilos adicionales -->
    <style>
        /* Transición suave para modo oscuro */
        html.dark {
            color-scheme: dark;
        }
        
        body {
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        .dark ::-webkit-scrollbar-track {
            background: #1e293b;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        
        /* Animación de carga */
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    
    <!-- Script para modo oscuro (cargar antes del body para evitar flash) -->
    <script>
        // Verificar preferencia guardada o del sistema
        if (localStorage.getItem('darkMode') === 'true' || 
            (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        
        // Función para toggle modo oscuro
        function toggleDarkMode() {
            const html = document.documentElement;
            html.classList.toggle('dark');
            localStorage.setItem('darkMode', html.classList.contains('dark'));
        }
    </script>
</head>
<body class="bg-light-bg dark:bg-dark-bg text-slate-800 dark:text-slate-100 min-h-screen transition-colors duration-300">