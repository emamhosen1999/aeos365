import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Pagination } from '@heroui/react';
import PageLayout from '@/Layouts/PageLayout';
import Breadcrumbs from '@/Components/Breadcrumbs';

const CmsCategory = ({ category, pages, breadcrumbs }) => {
    return (
        <>
            <Head title={category.name} />

            <div className="min-h-screen bg-white">
                {/* Breadcrumbs */}
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <div className="bg-default-50 border-b border-divider">
                        <div className="container mx-auto px-4 py-3">
                            <Breadcrumbs items={breadcrumbs} />
                        </div>
                    </div>
                )}

                {/* Category Header */}
                <div className="bg-gradient-to-r from-primary/10 to-primary/5 border-b border-primary/20 py-12">
                    <div className="container mx-auto px-4">
                        <div
                            className="w-12 h-12 rounded-lg mb-4"
                            style={{
                                backgroundColor: category.icon_color || '#3b82f6',
                            }}
                        >
                            <div className="flex items-center justify-center h-full text-white text-xl">
                                {category.icon || '📁'}
                            </div>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-bold mb-4 text-default-900">
                            {category.name}
                        </h1>
                        {category.description && (
                            <p className="text-lg text-default-600 max-w-3xl">
                                {category.description}
                            </p>
                        )}
                    </div>
                </div>

                {/* Pages Grid */}
                <div className="container mx-auto px-4 py-12">
                    {pages.data && pages.data.length > 0 ? (
                        <>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                                {pages.data.map((page) => (
                                    <Link
                                        key={page.id}
                                        href={route('cms.page.show', { slug: page.slug })}
                                    >
                                        <Card className="h-full border border-divider hover:shadow-lg transition-shadow cursor-pointer">
                                            <CardHeader className="flex flex-col items-start px-6 py-4 border-b border-divider">
                                                <h3 className="text-lg font-semibold text-default-900 line-clamp-2">
                                                    {page.title}
                                                </h3>
                                            </CardHeader>
                                            <CardBody className="gap-3 p-6">
                                                {page.description && (
                                                    <p className="text-default-600 line-clamp-3 text-sm">
                                                        {page.description}
                                                    </p>
                                                )}
                                                <div className="flex justify-between items-center pt-2 text-xs text-default-500">
                                                    <span>
                                                        {new Date(page.created_at).toLocaleDateString()}
                                                    </span>
                                                    <span className="text-primary">
                                                        Read more →
                                                    </span>
                                                </div>
                                            </CardBody>
                                        </Card>
                                    </Link>
                                ))}
                            </div>

                            {/* Pagination */}
                            {pages.last_page > 1 && (
                                <div className="flex justify-center">
                                    <Pagination
                                        total={pages.last_page}
                                        initialPage={pages.current_page}
                                        onChange={(page) => {
                                            window.location.href = route('cms.category', {
                                                slug: category.slug,
                                                page,
                                            });
                                        }}
                                    />
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center py-16">
                            <p className="text-default-500 text-lg">
                                No pages in this category yet.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
};

CmsCategory.layout = (page) => <PageLayout children={page} />;

export default CmsCategory;
