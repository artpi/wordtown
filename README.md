sudo apk add --no-cache imagemagick imagemagick-dev libpng-dev

## TODO

- Show the image in the post sidebar, with ability to generate / trigger the job.
- Split the job into 2 parts.
- Test on Atomic
- Add the post title to the tile
- Clicking (dbl?)
- Configuring the prompts
- Portals to other sites via trackbacks
- Smarter tile positioning
- Background tile


## Testing

1. Create a new post with content
2. `npm run wp-env run cli wp cron event run wordtown_generate_tile_for_post`