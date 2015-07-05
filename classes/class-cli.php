<?php
/**
 * Stream command for WP-CLI
 *
 * @see https://github.com/wp-cli/wp-cli
 */
namespace WP_Stream;

class CLI extends \WP_CLI_Command {

	/**
	 * Query a set of Stream records.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to wp_stream_query.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, count, json, json_pretty, csv. Default: table
	 *
	 * ## AVAILABLE FIELDS TO QUERY
	 *
	 * You can build a query from these fields:
	 *
	 * * author
	 * * author__in
	 * * author__not_in
	 * * author_role
	 * * author_role__in
	 * * author_role__not_in
	 * * date
	 * * date_from
	 * * date_to
	 * * date_after
	 * * date_before
	 * * ip
	 * * ip__in
	 * * ip__not_in
	 * * connector
	 * * connector__in
	 * * connector__not_in
	 * * context
	 * * context__in
	 * * context__not_in
	 * * action
	 * * action__in
	 * * action__not_in
	 * * search
	 * * search_field
	 * * record
	 * * record__in
	 * * record__not_in
	 * * records_per_page
	 * * paged
	 * * order
	 * * orderby
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each post:
	 *
	 * * created
	 * * ip
	 * * author
	 * * author_meta.user_login
	 * * author_role
	 * * summary
	 *
	 * These fields are optionally available:
	 *
	 * * ID
	 * * site_id
	 * * blog_id
	 * * object_id
	 * * connector
	 * * context
	 * * action
	 * * author_meta
	 * * stream_meta
	 * * meta.links.self
	 * * meta.links.collection
	 * * meta.score
	 * * meta.sort
	 *
	 * ## EXAMPLES
	 *
	 *     wp stream query --author_role__not_in=administrator --date_after=2015-01-01T12:00:00
	 *     wp stream query --author=1 --action=login --records_per_page=50 --fields=created
	 *
	 * @see WP_Stream_Query
	 * @see https://github.com/wp-stream/stream/wiki/WP-CLI-Command
	 * @see https://github.com/wp-stream/stream/wiki/Query-Reference
	 */
	public function query( $args, $assoc_args ) {
		$query_args        = array();
		$formatted_records = array();

		$this->connection();

		if ( empty( $assoc_args['fields'] ) ) {
			$fields = array( 'created', 'ip', 'author', 'author_meta.user_login', 'author_role', 'summary' );
		} else {
			$fields = explode( ',', $assoc_args['fields'] );
		}

		foreach ( $assoc_args as $key => $value ) {
			if ( 'format' === $key ) {
				continue;
			}

			$query_args[ $key ] = $value;
		}

		$query_args['fields'] = implode( ',', $fields );

		$records = wp_stream_query( $query_args );

		// Make structure Formatter compatible
		foreach ( (array) $records as $key => $record ) {
			$formatted_records[ $key ] = array();

			// Catch any fields missing in records
			foreach ( $fields as $field ) {
				if ( ! array_key_exists( $field, $record ) ) {
					$record->$field = null;
				}
			}

			foreach ( $record as $field_name => $field ) {

				$formatted_records[ $key ] = array_merge(
					$formatted_records[ $key ],
					$this->format_field( $field_name, $field )
				);
			}
		}

		if ( isset( $assoc_args['format'] ) && 'table' !== $assoc_args['format'] ) {
			if ( 'count' === $assoc_args['format'] ) {
				WP_CLI::line( count( $records ) );
			}

			if ( 'json' === $assoc_args['format'] ) {
				WP_CLI::line( wp_stream_json_encode( $formatted_records ) );
			}

			if ( 'json_pretty' === $assoc_args['format'] ) {
				if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
					WP_CLI::line( wp_stream_json_encode( $formatted_records ) ); // xss ok
				} else {
					WP_CLI::line( wp_stream_json_encode( $formatted_records, JSON_PRETTY_PRINT ) ); // xss ok
				}
			}

			if ( 'csv' === $assoc_args['format'] ) {
				WP_CLI::line( $this->csv_format( $formatted_records ) );
			}

			return;
		}

		$formatter = new \WP_CLI\Formatter(
			$assoc_args,
			$fields
		);

		$formatter->display_items( $formatted_records );
	}

	/**
	 * Convert any field to a flat array.
	 *
	 * @param string $name    The output array element name
	 * @param mixed  $object  Any value to be converted to an array
	 *
	 * @return array  The flat array
	 */
	private function format_field( $name, $object ) {
		$array = array();

		if ( is_object( $object ) ) {
			foreach ( $object as $key => $property ) {
				$array = array_merge( $array, $this->format_field( $name . '.' . $key, $property ) );
			}
		} elseif ( is_array( $object ) ) {
			$array[ $name ] = $object[0];
		} else {
			$array[ $name ] = $object;
		}

		return $array;
	}

	/**
	 * Convert an array of flat records to CSV
	 *
	 * @param array $array  The input array of records
	 *
	 * @return string  The CSV output
	 */
	private function csv_format( $array ) {
		$output = fopen( 'php://output', 'w' );

		foreach ( $array as $line ) {
			fputcsv( $output, $line );
		}

		fclose( $output );
	}

	/**
	 * Checks for a Stream connection and displays an error or success message.
	 *
	 * @return void
	 */
	private function connection() {
		$query = wp_stream_query( array( 'records_per_page' => 1, 'fields' => 'created' ) );

		if ( ! $query ) {
			WP_CLI::error( esc_html__( 'SITE IS DISCONNECTED', 'stream' ) );
		}
	}

}
