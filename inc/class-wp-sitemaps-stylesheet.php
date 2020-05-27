<?php
/**
 * Sitemaps: WP_Sitemaps_Stylesheet class
 *
 * This class provides the XSL stylesheets to style all sitemaps.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since 5.5.0
 */

/**
 * Stylesheet provider class.
 *
 * @since 5.5.0
 */
class WP_Sitemaps_Stylesheet {
	/**
	 * Renders the xsl stylesheet depending on whether its the sitemap index or not.
	 *
	 * @param string $type Stylesheet type. Either 'sitemap' or 'index'.
	 */
	public function render_stylesheet( $type ) {
		header( 'Content-type: application/xml; charset=UTF-8' );

		if ( 'sitemap' === $type ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All content escaped below.
			echo $this->get_sitemap_stylesheet();
		}

		if ( 'index' === $type ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All content escaped below.
			echo $this->get_sitemap_index_stylesheet();
		}

		exit;
	}

	/**
	 * Returns the escaped xsl for all sitemaps, except index.
	 *
	 * @since 5.5.0
	 */
	public function get_sitemap_stylesheet() {
		$css         = $this->get_stylesheet_css();
		$title       = esc_xml__( 'XML Sitemap', 'core-sitemaps' );
		$description = sprintf(
			/* translators: %s: URL to sitemaps documentation. */
			__( 'This XML Sitemap is generated by WordPress to make your content more visible for search engines. Learn more about XML sitemaps on <a href="%s">sitemaps.org</a>.', 'core-sitemaps' ),
			esc_xml__( 'https://www.sitemaps.org/', 'core-sitemaps' )
		);
		$text        = sprintf(
			/* translators: %s: number of URLs. */
			esc_xml__( 'This XML Sitemap contains %s URLs.', 'core-sitemaps' ),
			'<xsl:value-of select="count( sitemap:urlset/sitemap:url )"/>'
		);
		$columns     = $this->get_stylesheet_columns();

		$xsl_content = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
		version="1.0"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
		xmlns:wp="urn:wordpress.org/core-sitemaps"
		exclude-result-prefixes="sitemap wp"
		>
	<xsl:output method="html" encoding="UTF-8" indent="yes" />
	
	<!--
		Lookup table for columns.
	  -->
	<columns xmlns="urn:wordpress.org/core-sitemaps">
		$columns
	</columns>
	
	<!--
		Convert the columns lookup table to a node set and store it in a variable.
		
		If browsers could process XSLT 2.0 we'd just have the columns lookup table
		as the content template of this variable and wouldn't have to use
		{@link https://www.w3.org/TR/1999/REC-xslt-19991116#function-document document()}.
	  -->
	<xsl:variable name="columns" select="document( '' )/*/wp:columns" />
	
	<xsl:template match="/">
		<html>
			<head>
				<title>$title</title>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<style type="text/css">
					$css
				</style>
			</head>
			<body>
				<div id="sitemap__header">
					<h1>$title</h1>
					<p>$description</p>
				</div>
				<div id="sitemap__content">
					<p class="text">$text</p>

					<table id="sitemap__table">
						<thead>
							<xsl:apply-templates select="\$columns" mode="table-header" />
						</thead>
						<tbody>
							<xsl:apply-templates select="sitemap:urlset/sitemap:url" />
						</tbody>
					</table>
				</div>
			</body>
		</html>
	</xsl:template>
	
	<!--
		Output an HTML "tr" element for Q{http://www.sitemaps.org/schemas/sitemap/0.9}url.
	  -->
	<xsl:template match="sitemap:url">
		<tr>
			<xsl:apply-templates select="\$columns/wp:column" mode="table-data">
				<xsl:with-param name="current-url" select="current()" />
			</xsl:apply-templates>
		</tr>
	</xsl:template>
	
	<!--
		Output an HTML "a" element for Q{http://www.sitemaps.org/schemas/sitemap/0.9}loc.
	  -->
	<xsl:template match="sitemap:loc">
		<a href="{.}">
			<xsl:value-of select="." />
		</a>
	</xsl:template>
	
	<!--
		Output the text content of all other element children of Q{http://www.sitemaps.org/schemas/sitemap/0.9}url,
		regardless of namespace URI.
	  -->
	<xsl:template match="*">
		<xsl:value-of select="." />
	</xsl:template>
	
	<!--
		Output an HTML "tr" element with the column headers.
	  -->
	<xsl:template match="wp:columns" mode="table-header">
		<tr>
			<xsl:apply-templates select="wp:column" mode="table-header" />
		</tr>
	</xsl:template>
	
	<!--
		Output an HTML "th" element for a given column.
	  -->
	<xsl:template match="wp:column" mode="table-header">
		<th>
			<xsl:call-template name="add-css-class" />
			<xsl:value-of select="." />
		</th>
	</xsl:template>
	
	<!--
		Output an HTML "td" element for a given column.
		
		If the \$current-url does not have a child element for this column,
		and empty "td" element will be output.
		
		@param node \$current-url The current Q{http://www.sitemaps.org/schemas/sitemap/0.9}url element.
	  -->
	<xsl:template match="wp:column" mode="table-data">
		<xsl:param name="current-url" />
		
		<td>
			<xsl:call-template name="add-css-class" />
			<xsl:apply-templates select="\$current-url/*[namespace-uri() = current()/@namespace-uri and local-name() = current()/@local-name]" />
		</td>
	</xsl:template>
	
	<!--
		Add a CSS class attribute whose value includes the namespace URI and local-name of current column
		so that plugins can style them differently if they so choose.
	  -->
	<xsl:template name="add-css-class">
		<xsl:attribute name="class">
			<xsl:value-of select="concat( @namespace-uri, ' ', @local-name )" />
		</xsl:attribute>
	</xsl:template>
</xsl:stylesheet>

XSL;

		/**
		 * Filters the content of the sitemap stylesheet.
		 *
		 * @since 5.5.0
		 *
		 * @param string $xsl Full content for the xml stylesheet.
		 */
		return apply_filters( 'wp_sitemaps_stylesheet_content', $xsl_content );
	}

	/**
	 * Returns the escaped xsl for the index sitemaps.
	 *
	 * @since 5.5.0
	 */
	public function get_sitemap_index_stylesheet() {
		$css         = $this->get_stylesheet_css();
		$title       = esc_xml__( 'XML Sitemap', 'core-sitemaps' );
		$description = sprintf(
			/* translators: %s: URL to sitemaps documentation. */
			__( 'This XML Sitemap is generated by WordPress to make your content more visible for search engines. Learn more about XML sitemaps on <a href="%s">sitemaps.org</a>.', 'core-sitemaps' ),
			esc_xml__( 'https://www.sitemaps.org/', 'core-sitemaps' )
		);
		$text        = sprintf(
			/* translators: %s: number of URLs. */
			esc_xml__( 'This XML Sitemap contains %s URLs.', 'core-sitemaps' ),
			'<xsl:value-of select="count( sitemap:sitemapindex/sitemap:sitemap )"/>'
		);
		$url         = esc_xml__( 'URL', 'core-sitemaps' );

		$xsl_content = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
		version="1.0"
		xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		exclude-result-prefixes="sitemap"
		>
	<xsl:output method="html" encoding="UTF-8" indent="yes" />
	
	<xsl:template match="/">
		<html>
			<head>
				<title>$title</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<style type="text/css">
					$css
				</style>
			</head>
			<body>
				<div id="sitemap__header">
					<h1>$title</h1>
					<p>$description</p>
				</div>
				<div id="sitemap__content">
					<p class="text">$text</p>
					
					<table id="sitemap__table">
						<thead>
							<tr>
								<th>$url</th>
							</tr>
						</thead>
						<tbody>
							<xsl:apply-templates select="sitemap:sitemapindex/sitemap:sitemap" />
						</tbody>
					</table>
				</div>
			</body>
		</html>
	</xsl:template>
	
	<!--
		Output an HTML "tr" element for Q{http://www.sitemaps.org/schemas/sitemap/0.9}sitemap.
	  -->
	<xsl:template match="sitemap:sitemap">
		<tr>
			<xsl:apply-templates select="sitemap:loc" />
		</tr>
	</xsl:template>
	
	<!--
		Output an HTML "a" element for Q{http://www.sitemaps.org/schemas/sitemap/0.9}loc.
	  -->
	<xsl:template match="sitemap:loc">
		<td>
			<a href="{.}">
				<xsl:value-of select="." />
			</a>
		</td>
	</xsl:template>
</xsl:stylesheet>

XSL;

		/**
		 * Filters the content of the sitemap index stylesheet.
		 *
		 * @since 5.5.0
		 *
		 * @param string $xsl Full content for the xml stylesheet.
		 */
		return apply_filters( 'wp_sitemaps_index_stylesheet_content', $xsl_content );
	}

	/**
	 * Gets the CSS to be included in sitemap XSL stylesheets.
	 *
	 * @since 5.5.0
	 *
	 * @return string The CSS.
	 */
	public function get_stylesheet_css() {
		$css = '
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				color: #444;
			}

			#sitemap__table {
				border: solid 1px #ccc;
				border-collapse: collapse;
			}

			#sitemap__table tr th {
				text-align: left;
			}

			#sitemap__table tr td,
			#sitemap__table tr th {
				padding: 10px;
			}

			#sitemap__table tr:nth-child(odd) td {
				background-color: #eee;
			}

			a:hover {
				text-decoration: none;
			}';

		/**
		 * Filters the css only for the sitemap stylesheet.
		 *
		 * @since 5.5.0
		 *
		 * @param string $css CSS to be applied to default xsl file.
		 */
		return apply_filters( 'wp_sitemaps_stylesheet_css', $css );
	}

	/**
	 * Get the columns to be displayed by the sitemaps stylesheet.
	 *
	 * @return string
	 */
	protected function get_stylesheet_columns() {
		$default_columns = array(
			'http://www.sitemaps.org/schemas/sitemap/0.9' => array(
				'loc' => esc_xml__( 'URL', 'core-sitemaps' ),
			),
		);

		/**
		 * Filters the columns displayed by the sitemaps stylesheet.
		 *
		 * @param array $columns Keys are namespace URIs and values are
		 *                       arrays whose keys are local names and
		 *                       whose values are column heading text.
		 */
		$_columns = apply_filters( 'wp_sitemaps_stylesheet_columns', $default_columns );

		$columns = array();
		foreach ( $_columns as $namespace_uri => $namespace_columns ) {
			foreach ( $namespace_columns as $local_name => $heading_text ) {
				$columns[] = sprintf(
					'<column namespace-uri="%1$s" local-name="%2$s">%3$s</column>',
					$namespace_uri,
					$local_name,
					esc_xml( $heading_text )
				);
			}
		}

		return implode( "\n\t\t", $columns );
	}
}
