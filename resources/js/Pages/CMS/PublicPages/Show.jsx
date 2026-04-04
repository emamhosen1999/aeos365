import React, { useEffect } from 'react';
import { Head } from '@inertiajs/react';
import PageLayout from '@/Layouts/PageLayout';
import BlockRenderer from '@/Components/CMS/BlockRenderer';
import Breadcrumbs from '@/Components/Breadcrumbs';

const CmsPageShow = ({ page, seoMeta, breadcrumbs }) => {
    // Set page title and meta tags
    useEffect(() => {
        if (seoMeta) {
            // Set canonical URL
            if (seoMeta.canonical) {
                const canonicalLink = document.querySelector('link[rel="canonical"]');
                if (canonicalLink) {
                    canonicalLink.href = seoMeta.canonical;
                } else {
                    const link = document.createElement('link');
                    link.rel = 'canonical';
                    link.href = seoMeta.canonical;
                    document.head.appendChild(link);
                }
            }

            // Set Open Graph meta tags
            const setMetaTag = (property, content) => {
                if (!content) return;
                let tag = document.querySelector(`meta[property="${property}"]`);
                if (!tag) {
                    tag = document.createElement('meta');
                    tag.setAttribute('property', property);
                    document.head.appendChild(tag);
                }
                tag.setAttribute('content', content);
            };

            setMetaTag('og:title', seoMeta.ogTitle);
            setMetaTag('og:description', seoMeta.ogDescription);
            setMetaTag('og:image', seoMeta.ogImage);
            setMetaTag('og:url', seoMeta.ogUrl);
            setMetaTag('og:type', 'website');
        }
    }, [seoMeta]);

    return (
        <>
            <Head title={seoMeta?.title}>
                <meta name="description" content={seoMeta?.description} />
                {seoMeta?.keywords && <meta name="keywords" content={seoMeta.keywords} />}
                <meta name="robots" content={seoMeta?.robots || 'index, follow'} />
            </Head>

            <div className="min-h-screen bg-white">
                {/* Breadcrumbs */}
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <div className="bg-default-50 border-b border-divider sticky top-0 z-10">
                        <div className="container mx-auto px-4 py-3">
                            <Breadcrumbs items={breadcrumbs} />
                        </div>
                    </div>
                )}

                {/* Page Content */}
                <div className="container mx-auto px-4 py-12">
                    {/* Page Header */}
                    <div className="mb-12">
                        <h1 className="text-4xl md:text-5xl font-bold mb-4 text-default-900">
                            {page.title}
                        </h1>
                        {page.description && (
                            <p className="text-lg text-default-600 max-w-3xl">
                                {page.description}
                            </p>
                        )}
                    </div>

                    {/* Blocks */}
                    <div className="space-y-12">
                        {page.blocks && page.blocks.length > 0 ? (
                            page.blocks.map((block) => (
                                <div key={block.id} className="scroll-mt-24">
                                    <BlockRenderer block={block} />
                                </div>
                            ))
                        ) : (
                            <div className="text-center py-16">
                                <p className="text-default-500 text-lg">
                                    This page has no content yet.
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Footer Meta */}
                <div className="border-t border-divider bg-default-50 mt-16 py-8">
                    <div className="container mx-auto px-4">
                        <div className="flex justify-between items-center text-sm text-default-500">
                            <div>
                                {page.category && (
                                    <span>
                                        Category:{' '}
                                        <a
                                            href={route('cms.category', { slug: page.category.slug })}
                                            className="text-primary hover:underline"
                                        >
                                            {page.category.name}
                                        </a>
                                    </span>
                                )}
                            </div>
                            <div>
                                Last updated: {new Date(page.updated_at).toLocaleDateString()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

CmsPageShow.layout = (page) => <PageLayout children={page} />;

export default CmsPageShow;
