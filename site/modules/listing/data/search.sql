SELECT
	id,
	oid,
	name,
	address,
	city,
	state,
	description,
	latitude,
	longitude,
	SQRT(
		POW(longitude - {longitude}, 2) +
		POW(latitude - {latitude}, 2)
	) AS distance, -- we do a ugly distance calculation, correct number is calculated later
	radius,
	phone,
	promoted,
	likes,
	dislikes
FROM listing_companies
WHERE
	{support} = 1 AND
	active = 1
ORDER BY
	distance ASC
LIMIT {limit}
