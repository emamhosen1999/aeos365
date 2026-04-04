import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Input, Button, Card, CardBody, Pagination } from '@heroui/react';
import { MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import PageLayout from '@/Layouts/PageLayout';

const CmsSearch = ({ query, results }) => {
    const { data, setData, post } = useForm({
        q: query || '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        post(route('cms.search'));
    };

    return (
        <>
            <Head title={`Search: ${query}`} />

            <div className="min-h-screen bg-white">
                {/* Search Header */}
                <div className="bg-gradient-to-r from-primary/10 to-primary/5 border-b border-primary/20 py-12">
                    <div className="container mx-auto px-4">
                        <h1 className="text-4xl md:text-5xl font-bold mb-6 text-default-900">
                            Search CMS
                        </h1>

                        {/* Search Form */}
                        <form onSubmit={handleSearch} className="flex gap-3 max-w-2xl">
                            <Input
                                placeholder="Search pages..."
                                value={data.q}
                                onValueChange={(value) => setData('q', value)}
                                startContent={
                                    <MagnifyingGlassIcon className="w-4 h-4 text-default-400" />
                                }
                                size="lg"
                                radius="lg"
                                classNames={{
                                    inputWrapper: 'bg-white border border-divider',
                                }}
                            />
                            <Button
                                type="submit"
                                color="primary"
                                size="lg"
                                className="font-semibold"
                            >
                                Search
                            </Button>
                        </form>
                    </div>
                </div>

                {/* Search Results */}
                <div className="container mx-auto px-4 py-12">
                    {results.data && results.data.length > 0 ? (
                        <>
                            <div className="mb-6">
                                <p className="text-default-600">
                                    Found <span className="font-semibold">{results.total}</span>{' '}
                                    result{results.total !== 1 ? 's' : ''} for "
                                    <span className="font-semibold">{query}</span>"
                                </p>
                            </div>

                            <div className="space-y-4 mb-12">
                                {results.data.map((page) => (
                                    <Link
                                        key={page.id}
                                        href={route('cms.page.show', { slug: page.slug })}
                                    >
                                        <Card className="border border-divider hover:shadow-md transition-shadow cursor-pointer">
                                            <CardBody className="p-6">
                                                <h3 className="text-xl font-semibold text-primary hover:underline mb-2">
                                                    {page.title}
                                                </h3>
                                                <p className="text-default-600 mb-3 line-clamp-2">
                                                    {page.meta_description || page.content}
                                                </p>
                                                <div className="flex justify-between items-center text-sm text-default-500">
                                                    <span>{page.category?.name}</span>
                                                    <span>
                                                        Updated:{' '}
                                                        {new Date(
                                                            page.updated_at
                                                        ).toLocaleDateString()}
                                                    </span>
                                                </div>
                                            </CardBody>
                                        </Card>
                                    </Link>
                                ))}
                            </div>

                            {/* Pagination */}
                            {results.last_page > 1 && (
                                <div className="flex justify-center">
                                    <Pagination
                                        total={results.last_page}
                                        initialPage={results.current_page}
                                        onChange={(page) => {
                                            window.location.href = route('cms.search', {
                                                q: query,
                                                page,
                                            });
                                        }}
                                    />
                                </div>
                            )}
                        </>
                    ) : query ? (
                        <div className="text-center py-16">
                            <p className="text-default-500 text-lg mb-4">
                                No results found for "{query}"
                            </p>
                            <p className="text-default-400">
                                Try searching with different keywords
                            </p>
                        </div>
                    ) : (
                        <div className="text-center py-16">
                            <p className="text-default-500 text-lg">
                                Enter a search term to find pages
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
};

CmsSearch.layout = (page) => <PageLayout children={page} />;

export default CmsSearch;
