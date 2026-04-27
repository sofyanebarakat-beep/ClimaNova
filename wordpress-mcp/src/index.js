/**
 * WordPress MCP Server
 * Exposes the WordPress REST API as MCP tools for Claude.
 *
 * Tools: list/get/create/update/delete posts, pages, media,
 *        categories, tags, custom post types, site info.
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
	CallToolRequestSchema,
	ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import { readFileSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

// ── Load .env manually (no extra deps needed) ─────────────────────────────────
const __dir = dirname( fileURLToPath( import.meta.url ) );
const envPath = join( __dir, '..', '.env' );
if ( existsSync( envPath ) ) {
	const raw = readFileSync( envPath, 'utf8' );
	for ( const line of raw.split( '\n' ) ) {
		const trimmed = line.trim();
		if ( ! trimmed || trimmed.startsWith( '#' ) ) continue;
		const eq = trimmed.indexOf( '=' );
		if ( eq === -1 ) continue;
		const key = trimmed.slice( 0, eq ).trim();
		const val = trimmed.slice( eq + 1 ).trim().replace( /^["']|["']$/g, '' );
		if ( key && ! process.env[ key ] ) process.env[ key ] = val;
	}
}

// ── Validate required env vars ────────────────────────────────────────────────
const WP_BASE_URL   = ( process.env.WP_BASE_URL  || '' ).replace( /\/$/, '' );
const WP_JWT_TOKEN  = process.env.WP_JWT_TOKEN  || '';
const WP_APP_USER   = process.env.WP_APP_USER   || '';
const WP_APP_PASS   = process.env.WP_APP_PASSWORD || '';

if ( ! WP_BASE_URL ) {
	console.error( '[wordpress-mcp] ERROR: WP_BASE_URL is not set in .env' );
	process.exit( 1 );
}
if ( ! WP_JWT_TOKEN && ! ( WP_APP_USER && WP_APP_PASS ) ) {
	console.error( '[wordpress-mcp] ERROR: Set WP_JWT_TOKEN or WP_APP_USER + WP_APP_PASSWORD in .env' );
	process.exit( 1 );
}

// ── Auth header ───────────────────────────────────────────────────────────────
function authHeader() {
	if ( WP_JWT_TOKEN ) {
		return { Authorization: `Bearer ${ WP_JWT_TOKEN }` };
	}
	// Application Password fallback (Basic Auth)
	const b64 = Buffer.from( `${ WP_APP_USER }:${ WP_APP_PASS }` ).toString( 'base64' );
	return { Authorization: `Basic ${ b64 }` };
}

// ── WordPress REST API helper ─────────────────────────────────────────────────
async function wpFetch( path, options = {} ) {
	const isAbsolute = path.startsWith( 'http' );
	const url = isAbsolute ? path : `${ WP_BASE_URL }/wp-json${ path }`;

	const response = await fetch( url, {
		...options,
		headers: {
			...authHeader(),
			'Content-Type': 'application/json',
			...( options.headers || {} ),
		},
	} );

	const text = await response.text();
	let data;
	try {
		data = JSON.parse( text );
	} catch {
		data = text;
	}

	if ( ! response.ok ) {
		const msg = data?.message || data?.code || text || `HTTP ${ response.status }`;
		throw new Error( `WordPress API ${ response.status }: ${ msg }` );
	}

	// Expose total count via a meta property for list responses
	if ( Array.isArray( data ) ) {
		data._total = response.headers.get( 'X-WP-Total' );
		data._pages = response.headers.get( 'X-WP-TotalPages' );
	}

	return data;
}

// ── Build URLSearchParams from args (skip undefined/null) ─────────────────────
function params( obj ) {
	const p = new URLSearchParams();
	for ( const [ k, v ] of Object.entries( obj ) ) {
		if ( v !== undefined && v !== null && v !== '' ) p.set( k, v );
	}
	return p.toString() ? `?${ p }` : '';
}

// ── Helper: format a post for readable output ─────────────────────────────────
function formatPost( p ) {
	return {
		id:       p.id,
		title:    p.title?.rendered ?? p.title,
		status:   p.status,
		date:     p.date,
		modified: p.modified,
		link:     p.link,
		slug:     p.slug,
		excerpt:  p.excerpt?.rendered?.replace( /<[^>]+>/g, '' ).trim() ?? '',
	};
}

// ── MCP Server ────────────────────────────────────────────────────────────────
const server = new Server(
	{ name: 'wordpress-mcp', version: '1.0.0' },
	{ capabilities: { tools: {} } }
);

// ── Tool definitions ──────────────────────────────────────────────────────────
server.setRequestHandler( ListToolsRequestSchema, async () => ( {
	tools: [

		// ── Posts ──────────────────────────────────────────────────────────
		{
			name: 'wp_list_posts',
			description: 'List WordPress posts. Returns id, title, status, date, excerpt, and link.',
			inputSchema: {
				type: 'object',
				properties: {
					per_page: { type: 'number', description: 'Results per page (1-100)', default: 10 },
					page:     { type: 'number', description: 'Page number', default: 1 },
					status:   { type: 'string', description: 'publish | draft | pending | private | any', default: 'publish' },
					search:   { type: 'string', description: 'Search term' },
					category: { type: 'number', description: 'Filter by category ID' },
					tag:      { type: 'number', description: 'Filter by tag ID' },
					author:   { type: 'number', description: 'Filter by author ID' },
					orderby:  { type: 'string', description: 'date | title | modified | id | menu_order', default: 'date' },
					order:    { type: 'string', description: 'asc | desc', default: 'desc' },
				},
			},
		},

		{
			name: 'wp_get_post',
			description: 'Get the full content of a single WordPress post by ID.',
			inputSchema: {
				type: 'object',
				properties: {
					id: { type: 'number', description: 'Post ID' },
				},
				required: [ 'id' ],
			},
		},

		{
			name: 'wp_create_post',
			description: 'Create a new WordPress post.',
			inputSchema: {
				type: 'object',
				properties: {
					title:          { type: 'string', description: 'Post title' },
					content:        { type: 'string', description: 'Post content (HTML supported)' },
					status:         { type: 'string', description: 'publish | draft | pending', default: 'draft' },
					excerpt:        { type: 'string', description: 'Short excerpt' },
					slug:           { type: 'string', description: 'URL slug' },
					categories:     { type: 'array', items: { type: 'number' }, description: 'Category IDs' },
					tags:           { type: 'array', items: { type: 'number' }, description: 'Tag IDs' },
					featured_media: { type: 'number', description: 'Featured image media ID' },
				},
				required: [ 'title', 'content' ],
			},
		},

		{
			name: 'wp_update_post',
			description: 'Update an existing WordPress post. Only include fields you want to change.',
			inputSchema: {
				type: 'object',
				properties: {
					id:             { type: 'number', description: 'Post ID to update' },
					title:          { type: 'string' },
					content:        { type: 'string' },
					status:         { type: 'string', description: 'publish | draft | pending | private' },
					excerpt:        { type: 'string' },
					slug:           { type: 'string' },
					categories:     { type: 'array', items: { type: 'number' } },
					tags:           { type: 'array', items: { type: 'number' } },
					featured_media: { type: 'number' },
				},
				required: [ 'id' ],
			},
		},

		{
			name: 'wp_delete_post',
			description: 'Delete a WordPress post (moves to Trash by default).',
			inputSchema: {
				type: 'object',
				properties: {
					id:    { type: 'number', description: 'Post ID' },
					force: { type: 'boolean', description: 'Permanently delete (skip Trash)', default: false },
				},
				required: [ 'id' ],
			},
		},

		// ── Pages ──────────────────────────────────────────────────────────
		{
			name: 'wp_list_pages',
			description: 'List WordPress pages.',
			inputSchema: {
				type: 'object',
				properties: {
					per_page: { type: 'number', default: 20 },
					page:     { type: 'number', default: 1 },
					status:   { type: 'string', default: 'publish' },
					search:   { type: 'string' },
					parent:   { type: 'number', description: 'Filter by parent page ID (0 = top-level)' },
					orderby:  { type: 'string', default: 'menu_order' },
					order:    { type: 'string', default: 'asc' },
				},
			},
		},

		{
			name: 'wp_get_page',
			description: 'Get a single WordPress page by ID.',
			inputSchema: {
				type: 'object',
				properties: { id: { type: 'number' } },
				required: [ 'id' ],
			},
		},

		{
			name: 'wp_create_page',
			description: 'Create a new WordPress page.',
			inputSchema: {
				type: 'object',
				properties: {
					title:   { type: 'string' },
					content: { type: 'string' },
					status:  { type: 'string', default: 'draft' },
					slug:    { type: 'string' },
					parent:  { type: 'number', description: 'Parent page ID' },
				},
				required: [ 'title', 'content' ],
			},
		},

		{
			name: 'wp_update_page',
			description: 'Update an existing WordPress page.',
			inputSchema: {
				type: 'object',
				properties: {
					id:      { type: 'number' },
					title:   { type: 'string' },
					content: { type: 'string' },
					status:  { type: 'string' },
					slug:    { type: 'string' },
					parent:  { type: 'number' },
				},
				required: [ 'id' ],
			},
		},

		// ── Taxonomy ───────────────────────────────────────────────────────
		{
			name: 'wp_list_categories',
			description: 'List WordPress categories.',
			inputSchema: {
				type: 'object',
				properties: {
					per_page: { type: 'number', default: 100 },
					search:   { type: 'string' },
					parent:   { type: 'number' },
					hide_empty: { type: 'boolean', default: false },
				},
			},
		},

		{
			name: 'wp_create_category',
			description: 'Create a new WordPress category.',
			inputSchema: {
				type: 'object',
				properties: {
					name:        { type: 'string' },
					slug:        { type: 'string' },
					description: { type: 'string' },
					parent:      { type: 'number' },
				},
				required: [ 'name' ],
			},
		},

		{
			name: 'wp_list_tags',
			description: 'List WordPress tags.',
			inputSchema: {
				type: 'object',
				properties: {
					per_page:   { type: 'number', default: 100 },
					search:     { type: 'string' },
					hide_empty: { type: 'boolean', default: false },
				},
			},
		},

		// ── Media ──────────────────────────────────────────────────────────
		{
			name: 'wp_list_media',
			description: 'List WordPress media library items.',
			inputSchema: {
				type: 'object',
				properties: {
					per_page:   { type: 'number', default: 20 },
					page:       { type: 'number', default: 1 },
					media_type: { type: 'string', description: 'image | video | audio | application' },
					search:     { type: 'string' },
					mime_type:  { type: 'string', description: 'e.g. image/jpeg' },
				},
			},
		},

		// ── Custom Post Types ──────────────────────────────────────────────
		{
			name: 'wp_list_cpt',
			description: 'List posts of any registered custom post type (e.g. climanova_service, climanova_project).',
			inputSchema: {
				type: 'object',
				properties: {
					post_type: { type: 'string', description: 'Custom post type REST slug (e.g. climanova_service)' },
					per_page:  { type: 'number', default: 20 },
					status:    { type: 'string', default: 'publish' },
					search:    { type: 'string' },
				},
				required: [ 'post_type' ],
			},
		},

		{
			name: 'wp_create_cpt',
			description: 'Create a post of a custom post type.',
			inputSchema: {
				type: 'object',
				properties: {
					post_type: { type: 'string', description: 'Custom post type REST slug' },
					title:     { type: 'string' },
					content:   { type: 'string' },
					status:    { type: 'string', default: 'publish' },
					meta:      { type: 'object', description: 'Custom meta fields as key:value pairs' },
				},
				required: [ 'post_type', 'title' ],
			},
		},

		{
			name: 'wp_update_cpt',
			description: 'Update a custom post type entry.',
			inputSchema: {
				type: 'object',
				properties: {
					post_type: { type: 'string' },
					id:        { type: 'number' },
					title:     { type: 'string' },
					content:   { type: 'string' },
					status:    { type: 'string' },
					meta:      { type: 'object' },
				},
				required: [ 'post_type', 'id' ],
			},
		},

		// ── Search & Site ──────────────────────────────────────────────────
		{
			name: 'wp_search',
			description: 'Full-text search across WordPress posts and pages.',
			inputSchema: {
				type: 'object',
				properties: {
					query:    { type: 'string', description: 'Search term' },
					type:     { type: 'string', description: 'post | page | any', default: 'any' },
					per_page: { type: 'number', default: 10 },
					status:   { type: 'string', default: 'publish' },
				},
				required: [ 'query' ],
			},
		},

		{
			name: 'wp_get_site_info',
			description: 'Get WordPress site information: name, description, URL, timezone, REST namespaces.',
			inputSchema: { type: 'object', properties: {} },
		},

		{
			name: 'wp_list_users',
			description: 'List WordPress users (requires Editor or Admin role).',
			inputSchema: {
				type: 'object',
				properties: {
					per_page: { type: 'number', default: 20 },
					search:   { type: 'string' },
					roles:    { type: 'string', description: 'Comma-separated roles: administrator,editor,author' },
				},
			},
		},

	],
} ) );

// ── Tool handlers ─────────────────────────────────────────────────────────────
server.setRequestHandler( CallToolRequestSchema, async ( request ) => {
	const { name, arguments: args = {} } = request.params;

	try {
		let result;

		switch ( name ) {

			// ── Posts ────────────────────────────────────────────────────────
			case 'wp_list_posts': {
				const q = params( {
					per_page:   args.per_page ?? 10,
					page:       args.page,
					status:     args.status || 'publish',
					search:     args.search,
					categories: args.category,
					tags:       args.tag,
					author:     args.author,
					orderby:    args.orderby || 'date',
					order:      args.order   || 'desc',
					_fields:    'id,title,status,date,modified,excerpt,link,slug,categories,tags',
				} );
				const posts = await wpFetch( `/wp/v2/posts${ q }` );
				const total = posts._total;
				const pages = posts._pages;
				result = {
					total:   Number( total ),
					pages:   Number( pages ),
					showing: posts.length,
					posts:   posts.map( formatPost ),
				};
				break;
			}

			case 'wp_get_post': {
				const post = await wpFetch( `/wp/v2/posts/${ args.id }` );
				result = {
					...formatPost( post ),
					content:        post.content?.rendered ?? '',
					featured_media: post.featured_media,
					categories:     post.categories,
					tags:           post.tags,
					author:         post.author,
					comment_status: post.comment_status,
					meta:           post.meta,
				};
				break;
			}

			case 'wp_create_post': {
				const body = {
					title:   args.title,
					content: args.content,
					status:  args.status || 'draft',
				};
				if ( args.excerpt )        body.excerpt        = args.excerpt;
				if ( args.slug )           body.slug           = args.slug;
				if ( args.categories )     body.categories     = args.categories;
				if ( args.tags )           body.tags           = args.tags;
				if ( args.featured_media ) body.featured_media = args.featured_media;

				const post = await wpFetch( '/wp/v2/posts', { method: 'POST', body: JSON.stringify( body ) } );
				result = { message: 'Post created successfully', ...formatPost( post ) };
				break;
			}

			case 'wp_update_post': {
				const { id, ...updates } = args;
				const post = await wpFetch( `/wp/v2/posts/${ id }`, { method: 'POST', body: JSON.stringify( updates ) } );
				result = { message: `Post ${ id } updated`, ...formatPost( post ) };
				break;
			}

			case 'wp_delete_post': {
				const res = await wpFetch( `/wp/v2/posts/${ args.id }${ params( { force: args.force } ) }`, { method: 'DELETE' } );
				result = { message: args.force ? `Post ${ args.id } permanently deleted` : `Post ${ args.id } moved to Trash`, id: res.id };
				break;
			}

			// ── Pages ────────────────────────────────────────────────────────
			case 'wp_list_pages': {
				const q = params( {
					per_page: args.per_page ?? 20,
					page:     args.page,
					status:   args.status || 'publish',
					search:   args.search,
					parent:   args.parent,
					orderby:  args.orderby || 'menu_order',
					order:    args.order   || 'asc',
					_fields:  'id,title,status,date,modified,link,slug,parent',
				} );
				const pages = await wpFetch( `/wp/v2/pages${ q }` );
				result = { total: Number( pages._total ), pages: pages.map( formatPost ) };
				break;
			}

			case 'wp_get_page': {
				const page = await wpFetch( `/wp/v2/pages/${ args.id }` );
				result = { ...formatPost( page ), content: page.content?.rendered ?? '', parent: page.parent };
				break;
			}

			case 'wp_create_page': {
				const body = { title: args.title, content: args.content, status: args.status || 'draft' };
				if ( args.slug )   body.slug   = args.slug;
				if ( args.parent ) body.parent = args.parent;
				const page = await wpFetch( '/wp/v2/pages', { method: 'POST', body: JSON.stringify( body ) } );
				result = { message: 'Page created', ...formatPost( page ) };
				break;
			}

			case 'wp_update_page': {
				const { id, ...updates } = args;
				const page = await wpFetch( `/wp/v2/pages/${ id }`, { method: 'POST', body: JSON.stringify( updates ) } );
				result = { message: `Page ${ id } updated`, ...formatPost( page ) };
				break;
			}

			// ── Taxonomy ─────────────────────────────────────────────────────
			case 'wp_list_categories': {
				const q = params( { per_page: args.per_page ?? 100, search: args.search, parent: args.parent, hide_empty: args.hide_empty, _fields: 'id,name,slug,count,parent,description' } );
				result = await wpFetch( `/wp/v2/categories${ q }` );
				break;
			}

			case 'wp_create_category': {
				result = await wpFetch( '/wp/v2/categories', { method: 'POST', body: JSON.stringify( args ) } );
				break;
			}

			case 'wp_list_tags': {
				const q = params( { per_page: args.per_page ?? 100, search: args.search, hide_empty: args.hide_empty, _fields: 'id,name,slug,count' } );
				result = await wpFetch( `/wp/v2/tags${ q }` );
				break;
			}

			// ── Media ────────────────────────────────────────────────────────
			case 'wp_list_media': {
				const q = params( {
					per_page:   args.per_page ?? 20,
					page:       args.page,
					media_type: args.media_type,
					search:     args.search,
					mime_type:  args.mime_type,
					_fields:    'id,title,source_url,mime_type,date,alt_text,media_details',
				} );
				const media = await wpFetch( `/wp/v2/media${ q }` );
				result = {
					total: Number( media._total ),
					items: media.map( m => ( {
						id:         m.id,
						title:      m.title?.rendered ?? m.title,
						url:        m.source_url,
						mime_type:  m.mime_type,
						date:       m.date,
						alt_text:   m.alt_text,
						dimensions: m.media_details ? `${ m.media_details.width }x${ m.media_details.height }` : null,
					} ) ),
				};
				break;
			}

			// ── Custom Post Types ─────────────────────────────────────────────
			case 'wp_list_cpt': {
				const q = params( { per_page: args.per_page ?? 20, status: args.status || 'publish', search: args.search } );
				const posts = await wpFetch( `/wp/v2/${ args.post_type }${ q }` );
				result = { total: Number( posts._total ), posts: posts.map( formatPost ) };
				break;
			}

			case 'wp_create_cpt': {
				const body = { title: args.title, content: args.content || '', status: args.status || 'publish' };
				if ( args.meta ) body.meta = args.meta;
				const post = await wpFetch( `/wp/v2/${ args.post_type }`, { method: 'POST', body: JSON.stringify( body ) } );
				result = { message: `${ args.post_type } created`, ...formatPost( post ) };
				break;
			}

			case 'wp_update_cpt': {
				const { post_type, id, ...updates } = args;
				const post = await wpFetch( `/wp/v2/${ post_type }/${ id }`, { method: 'POST', body: JSON.stringify( updates ) } );
				result = { message: `${ post_type } ${ id } updated`, ...formatPost( post ) };
				break;
			}

			// ── Search & Site ─────────────────────────────────────────────────
			case 'wp_search': {
				const results = [];
				const types   = args.type === 'page' ? [ 'pages' ] : args.type === 'post' ? [ 'posts' ] : [ 'posts', 'pages' ];
				for ( const t of types ) {
					const q = params( { search: args.query, per_page: args.per_page ?? 10, status: args.status || 'publish', _fields: 'id,title,status,date,link,slug,excerpt' } );
					const items = await wpFetch( `/wp/v2/${ t }${ q }` );
					results.push( ...items.map( p => ( { type: t.slice( 0, -1 ), ...formatPost( p ) } ) ) );
				}
				result = { count: results.length, results };
				break;
			}

			case 'wp_get_site_info': {
				const info = await wpFetch( '/' );
				result = {
					name:             info.name,
					description:      info.description,
					url:              info.url,
					home:             info.home,
					gmt_offset:       info.gmt_offset,
					timezone_string:  info.timezone_string,
					date_format:      info.date_format,
					time_format:      info.time_format,
					start_of_week:    info.start_of_week,
					language:         info.language,
					namespaces:       info.namespaces,
				};
				break;
			}

			case 'wp_list_users': {
				const q = params( { per_page: args.per_page ?? 20, search: args.search, roles: args.roles, _fields: 'id,name,slug,email,registered_date,roles,link' } );
				const users = await wpFetch( `/wp/v2/users${ q }` );
				result = users;
				break;
			}

			default:
				throw new Error( `Unknown tool: ${ name }` );
		}

		return {
			content: [ { type: 'text', text: JSON.stringify( result, null, 2 ) } ],
		};

	} catch ( error ) {
		return {
			content: [ { type: 'text', text: `❌ Error: ${ error.message }` } ],
			isError: true,
		};
	}
} );

// ── Start ─────────────────────────────────────────────────────────────────────
const transport = new StdioServerTransport();
await server.connect( transport );
console.error( `[wordpress-mcp] Running — connected to ${ WP_BASE_URL }` );
