import React from 'react';
import { ChevronRightIcon } from '@heroicons/react/24/outline';

const Breadcrumbs = ({ items = [] }) => {
    if (!items || items.length === 0) {
        return null;
    }

    return (
        <nav className="flex" aria-label="Breadcrumb">
            <ol className="flex items-center gap-2 flex-wrap">
                {items.map((item, index) => (
                    <li key={index} className="flex items-center">
                        {index > 0 && (
                            <ChevronRightIcon className="w-4 h-4 text-default-400 mx-2" />
                        )}

                        {item.url ? (
                            <a
                                href={item.url}
                                className="text-primary hover:underline transition-colors"
                            >
                                {item.title}
                            </a>
                        ) : (
                            <span
                                className={`${
                                    item.active
                                        ? 'text-default-900 font-semibold'
                                        : 'text-default-600'
                                }`}
                            >
                                {item.title}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
};

export default Breadcrumbs;
