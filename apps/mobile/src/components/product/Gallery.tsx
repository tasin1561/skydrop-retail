// Swipeable media gallery (photos + HLS videos) with travelling dots.
import { useState } from "react";
import { FlatList, Pressable, StyleSheet, View, useWindowDimensions } from "react-native";
import Animated, { useAnimatedScrollHandler, useAnimatedStyle, useSharedValue, interpolate, Extrapolation, type SharedValue } from "react-native-reanimated";
import { Image } from "expo-image";
import { useVideoPlayer, VideoView } from "expo-video";
import { useEvent } from "expo";
import { ProductImage } from "../ui";
import { Icon } from "../Icon";
import { sized } from "@/lib/variants";
import { useTheme } from "@/theme";
import { useT } from "@/i18n";

export type Media = { kind: "image"; src: string } | { kind: "video"; hls: string; thumb: string | null };

const AnimatedFlatList = Animated.createAnimatedComponent(FlatList<Media>);

export function Gallery({ media, placeholder, title }: { media: Media[]; placeholder?: { hue: number; icon: string }; title: string }) {
  const { t } = useT();
  const { width } = useWindowDimensions();
  const x = useSharedValue(0);
  const [index, setIndex] = useState(0);
  const onScroll = useAnimatedScrollHandler((e) => {
    x.value = e.contentOffset.x;
  });

  if (!media.length) return <ProductImage uri={null} placeholder={placeholder} style={{ width, height: width }} label={title} />;

  return (
    <View>
      <AnimatedFlatList
        data={media}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        keyExtractor={(m, i) => (m.kind === "image" ? m.src : m.hls) + i}
        onScroll={onScroll}
        scrollEventThrottle={16}
        onMomentumScrollEnd={(e) => setIndex(Math.round(e.nativeEvent.contentOffset.x / width))}
        renderItem={({ item, index: i }) =>
          item.kind === "image" ? (
            <View style={{ width, height: width, backgroundColor: "#fff" }}>
              <Image
                source={{ uri: sized(item.src, "SL1000") ?? item.src }}
                style={{ flex: 1 }}
                contentFit="contain"
                transition={200}
                cachePolicy="memory-disk"
                accessibilityLabel={t("m.photoOf", { title, n: i + 1, total: media.length })}
              />
            </View>
          ) : (
            <VideoSlide hls={item.hls} thumb={item.thumb} size={width} active={i === index} />
          )
        }
        initialNumToRender={2}
        windowSize={3}
      />
      {media.length > 1 && (
        <View style={styles.dots} accessibilityLabel={t("m.itemOf", { n: index + 1, total: media.length })}>
          {media.map((m, i) => (
            <Dot key={i} i={i} x={x} width={width} video={m.kind === "video"} />
          ))}
        </View>
      )}
    </View>
  );
}

function Dot({ i, x, width, video }: { i: number; x: SharedValue<number>; width: number; video: boolean }) {
  const { c } = useTheme();
  const a = useAnimatedStyle(() => {
    const d = Math.abs(x.value / width - i);
    return {
      opacity: interpolate(d, [0, 1], [1, 0.35], Extrapolation.CLAMP),
      transform: [{ scaleX: interpolate(d, [0, 1], [2.4, 1], Extrapolation.CLAMP) }],
    };
  });
  return <Animated.View style={[styles.dot, { backgroundColor: video ? c.coral : c.ink }, a]} />;
}

// Native HLS playback: AVPlayer on iOS, ExoPlayer (Media3) on Android.
function VideoSlide({ hls, thumb, size, active }: { hls: string; thumb: string | null; size: number; active: boolean }) {
  const { t } = useT();
  const [started, setStarted] = useState(false);
  if (!started || !active)
    return (
      <Pressable onPress={() => setStarted(true)} accessibilityRole="button" accessibilityLabel={t("m.playVideo")} style={{ width: size, height: size, backgroundColor: "#000" }}>
        {thumb && <Image source={{ uri: thumb }} style={StyleSheet.absoluteFill} contentFit="cover" />}
        <View style={styles.play}>
          <Icon name="play" size={28} color="#fff" />
        </View>
      </Pressable>
    );
  return <Player hls={hls} size={size} />;
}

function Player({ hls, size }: { hls: string; size: number }) {
  const player = useVideoPlayer({ uri: hls, contentType: "hls" }, (p) => {
    p.loop = true;
    p.muted = false;
    p.play();
  });
  const { status } = useEvent(player, "statusChange", { status: player.status });
  return (
    <View style={{ width: size, height: size, backgroundColor: "#000" }}>
      <VideoView player={player} style={{ flex: 1 }} contentFit="contain" nativeControls fullscreenOptions={{ enable: true }} allowsPictureInPicture={false} />
      {status === "error" && (
        <View style={[StyleSheet.absoluteFill, { alignItems: "center", justifyContent: "center" }]}>
          <Icon name="video-off" size={28} color="#fff" />
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  dots: { position: "absolute", bottom: 12, alignSelf: "center", flexDirection: "row", gap: 10, backgroundColor: "rgba(255,255,255,0.85)", paddingHorizontal: 10, paddingVertical: 6, borderRadius: 999 },
  dot: { width: 6, height: 6, borderRadius: 3 },
  play: { position: "absolute", alignSelf: "center", top: "42%", width: 64, height: 64, borderRadius: 32, backgroundColor: "rgba(0,0,0,0.55)", alignItems: "center", justifyContent: "center" },
});
