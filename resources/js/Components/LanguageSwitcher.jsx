import React, { useMemo } from 'react';
import {
    Dropdown,
    DropdownTrigger,
    DropdownMenu,
    DropdownItem,
    Button,
    Badge,
} from '@heroui/react';
import { GlobeAltIcon } from '@heroicons/react/24/outline';

/**
 * LanguageSwitcher Component
 * 
 * Displays available languages and allows users to switch between them.
 * Supports 8 languages: English, Spanish, French, German, Italian, Portuguese, Japanese, Chinese
 * 
 * Preserves the current path while switching locales:
 * /en/cms/page/about → /es/cms/page/about (locale switch)
 */
const LanguageSwitcher = ({ currentLocale = 'en' }) => {
    const SUPPORTED_LANGUAGES = useMemo(() => [
        { code: 'en', name: 'English', flag: '🇺🇸' },
        { code: 'es', name: 'Español', flag: '🇪🇸' },
        { code: 'fr', name: 'Français', flag: '🇫🇷' },
        { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
        { code: 'it', name: 'Italiano', flag: '🇮🇹' },
        { code: 'pt', name: 'Português', flag: '🇵🇹' },
        { code: 'ja', name: '日本語', flag: '🇯🇵' },
        { code: 'zh', name: '中文', flag: '🇨🇳' },
    ], []);

    const currentLanguage = useMemo(() => {
        return SUPPORTED_LANGUAGES.find(lang => lang.code === currentLocale) || SUPPORTED_LANGUAGES[0];
    }, [currentLocale, SUPPORTED_LANGUAGES]);

    /**
     * Switch to a different language while preserving the current path
     */
    const switchLanguage = (languageCode) => {
        if (languageCode === currentLocale) {
            return;
        }

        const currentPath = window.location.pathname;

        // Handle different path patterns:
        // 1. /en/cms/page/about → /es/cms/page/about
        // 2. /cms/page/about → /es/cms/page/about
        // 3. / → /es/

        // Check if current path has locale prefix
        const pathSegments = currentPath.split('/').filter(Boolean);
        const isLocalized = SUPPORTED_LANGUAGES.some(lang => lang.code === pathSegments[0]);

        let newPath;

        if (isLocalized) {
            // Replace existing locale with new one
            pathSegments[0] = languageCode;
            newPath = '/' + pathSegments.join('/');
        } else {
            // Add locale prefix to non-localized path
            if (currentPath === '/') {
                newPath = `/${languageCode}/`;
            } else {
                newPath = `/${languageCode}${currentPath}`;
            }
        }

        // Preserve query string if present
        const queryString = window.location.search;
        window.location.href = newPath + queryString;
    };

    return (
        <Dropdown>
            <DropdownTrigger>
                <Button
                    isIconOnly
                    variant="flat"
                    size="sm"
                    className="flex items-center gap-2"
                    title="Switch language"
                >
                    <span className="text-lg">{currentLanguage.flag}</span>
                    <GlobeAltIcon className="w-4 h-4" />
                </Button>
            </DropdownTrigger>

            <DropdownMenu
                aria-label="Language selection"
                onAction={(key) => switchLanguage(key.toString())}
                selectedKeys={[currentLocale]}
                selectionMode="single"
            >
                {SUPPORTED_LANGUAGES.map(language => (
                    <DropdownItem
                        key={language.code}
                        className={currentLocale === language.code ? 'bg-primary/10' : ''}
                        startContent={<span className="text-lg">{language.flag}</span>}
                        textValue={language.name}
                    >
                        <div className="flex items-center justify-between w-full">
                            <span>{language.name}</span>
                            {currentLocale === language.code && (
                                <Badge
                                    content="✓"
                                    color="primary"
                                    size="sm"
                                    className="ml-2"
                                />
                            )}
                        </div>
                    </DropdownItem>
                ))}
            </DropdownMenu>
        </Dropdown>
    );
};

export default LanguageSwitcher;
