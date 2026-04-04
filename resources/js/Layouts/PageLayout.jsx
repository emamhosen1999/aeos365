import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@heroui/react';
import Sidebar from '@/Components/Sidebar';
import Header from '@/Components/Header';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useAuth } from '@/Hooks/useAuth';

const PageLayout = ({ children, title }) => {
    const { auth } = useAuth();

    // Extract locale from URL path
    // Pattern: /en/cms/page/about or /cms/page/about
    const getCurrentLocale = () => {
        const pathSegments = window.location.pathname.split('/').filter(Boolean);
        const supportedLocales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'ja', 'zh'];
        
        if (pathSegments.length > 0 && supportedLocales.includes(pathSegments[0])) {
            return pathSegments[0];
        }
        
        // Default to 'en' if no locale prefix found
        return 'en';
    };

    const currentLocale = getCurrentLocale();

    return (
        <>
            {title && <Head title={title} />}

            <div className="min-h-screen bg-default-50 flex flex-col">
                {/* Header */}
                <Header />

                {/* Main Content */}
                <div className="flex-1">
                    {children}
                </div>

                {/* Footer */}
                <footer className="border-t border-divider bg-default-100 mt-16">
                    <div className="container mx-auto px-4 py-12">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                            {/* About */}
                            <div>
                                <h5 className="font-semibold text-default-900 mb-4">About</h5>
                                <p className="text-sm text-default-600 line-clamp-3">
                                    Aero Enterprise Suite - Complete ERP solution for modern businesses
                                </p>
                            </div>

                            {/* Product */}
                            <div>
                                <h5 className="font-semibold text-default-900 mb-4">Product</h5>
                                <ul className="space-y-2 text-sm text-default-600">
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Features
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Pricing
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Security
                                        </Link>
                                    </li>
                                </ul>
                            </div>

                            {/* Resources */}
                            <div>
                                <h5 className="font-semibold text-default-900 mb-4">Resources</h5>
                                <ul className="space-y-2 text-sm text-default-600">
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Documentation
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Support
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href={route('cms.search')} className="hover:text-primary">
                                            Search
                                        </Link>
                                    </li>
                                </ul>
                            </div>

                            {/* Legal */}
                            <div>
                                <h5 className="font-semibold text-default-900 mb-4">Company</h5>
                                <ul className="space-y-2 text-sm text-default-600">
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Privacy
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Terms
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/" className="hover:text-primary">
                                            Contact
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div className="border-t border-divider pt-8 flex justify-between items-center text-sm text-default-600">
                            <p>
                                &copy; 2026 Aero Enterprise Suite. All rights reserved.
                            </p>
                            <div className="flex gap-4 items-center">
                                <Link href="/" className="hover:text-primary">
                                    Twitter
                                </Link>
                                <Link href="/" className="hover:text-primary">
                                    LinkedIn
                                </Link>
                                <Link href="/" className="hover:text-primary">
                                    GitHub
                                </Link>
                                <div className="border-l border-divider pl-4 ml-4">
                                    <LanguageSwitcher currentLocale={currentLocale} />
                                </div>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
};

export default PageLayout;
