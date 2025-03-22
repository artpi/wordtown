WordTown is a WordPress plugin that generates an isometric world of your posts.
For each post you publish, it will generate a tile.

## Development

For fully functioning dev environent, your docker needs `imagemagic` with png suport. This is not available in the wp-env docker image so you need to install it.

```
npm run wp-env run cli /bin/bash
sudo apk add --no-cache imagemagick imagemagick-dev libpng-dev
```

## Future Improvements

- Portals to other sites via trackbacks. When site links to my post and it has WordTown enabled, we could introduce a waysign to redirect to that site.
- Smarter tile positioning
- Background tile
